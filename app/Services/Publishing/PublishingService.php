<?php

namespace App\Services\Publishing;

use App\Enums\ContentStatus;
use App\Enums\RevisionKind;
use App\Enums\WorkflowAction;
use App\Models\Page;
use App\Models\User;
use App\Services\ActivityLog\ActivityLogger;
use App\Services\Cache\CacheVersions;
use App\Services\Revisions\RevisionService;
use App\Services\Seo\RedirectService;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * The editorial workflow (CMS-ARCHITECTURE.md §4.1):
 *
 *   Draft ─submit→ In review ─approve→ Approved ─publish→ Published ─archive→ Archived
 *     ▲                │  request changes  │                  │ unpublish → Draft
 *     └────────────────┴───────────────────┘                  restore (from Archived) → Draft
 *
 * Every transition is permission-checked here, recorded in the activity log and — for
 * publishing — snapshotted into an immutable revision. Pages use staged publishing: the
 * published snapshot stays live while the working copy is edited.
 */
class PublishingService
{
    public function __construct(
        private readonly RevisionService $revisions,
        private readonly RedirectService $redirects,
        private readonly ActivityLogger $logger,
        private readonly CacheVersions $cache,
    ) {}

    /**
     * Actions the user may perform on the page right now (for the admin publish box).
     *
     * @return list<WorkflowAction>
     */
    public function availableActions(Page $page, User $user): array
    {
        return array_values(array_filter(
            WorkflowAction::cases(),
            fn (WorkflowAction $action) => $this->allowedFromState($page, $action) && $this->userMay($page, $action, $user),
        ));
    }

    /**
     * @param  array{note?: ?string, publish_at?: ?CarbonInterface}  $options
     *
     * @throws AuthorizationException|ValidationException
     */
    public function transition(Page $page, WorkflowAction $action, ?User $user, array $options = []): Page
    {
        // $user === null means the system (scheduler); people are always permission-checked.
        if ($user !== null && ! $this->userMay($page, $action, $user)) {
            throw new AuthorizationException(__('You are not allowed to :action this page.', ['action' => strtolower($action->label())]));
        }

        if (! $this->allowedFromState($page, $action)) {
            throw ValidationException::withMessages([
                'action' => __('":action" is not possible while the page is :status.', [
                    'action' => $action->label(),
                    'status' => strtolower($page->status->label()),
                ]),
            ]);
        }

        $from = $page->status;

        DB::transaction(function () use ($page, $action, $user, $options) {
            match ($action) {
                WorkflowAction::Submit => $page->status = ContentStatus::InReview,
                WorkflowAction::Approve => $page->status = ContentStatus::Approved,
                WorkflowAction::RequestChanges => $page->status = ContentStatus::Draft,
                WorkflowAction::Publish => $this->publish($page, $user),
                WorkflowAction::Schedule => $this->schedule($page, $options['publish_at'] ?? null),
                WorkflowAction::Unschedule => $page->publish_at = null,
                WorkflowAction::Unpublish => $this->takeOffline($page, ContentStatus::Draft, $user),
                WorkflowAction::Archive => $this->takeOffline($page, ContentStatus::Archived, $user),
                WorkflowAction::Restore => $page->status = ContentStatus::Draft,
            };

            if ($action !== WorkflowAction::Publish) {
                $page->updated_by = $user?->getKey() ?? $page->updated_by;
                $page->save();
            }
        });

        $this->logger->log('workflow.'.$action->value, $page, array_filter([
            'from' => $from->value,
            'to' => $page->status->value,
            'note' => isset($options['note']) ? mb_substr((string) $options['note'], 0, 1000) : null,
            'publish_at' => $page->publish_at?->toIso8601String(),
            'path' => $page->published_path,
        ]), $user);

        return $page;
    }

    /**
     * Remove a page from the public site (unpublish, archive, delete).
     */
    public function takeOffline(Page $page, ContentStatus $status, ?User $user): void
    {
        if ($page->isLive() && $page->children()->live()->exists()) {
            throw ValidationException::withMessages([
                'action' => __('Unpublish or archive the published sub-pages of ":title" first.', ['title' => $page->title]),
            ]);
        }

        $page->published_revision_id = null;
        $page->published_path = null;
        $page->publish_at = null;
        $page->has_unpublished_changes = true;
        $page->status = $status;
        $page->updated_by = $user?->getKey() ?? $page->updated_by;
        $page->save();

        $this->cache->bump('pages');
    }

    /**
     * Publish every approved page whose scheduled time has passed. Returns the count.
     */
    public function publishDue(): int
    {
        $count = 0;

        Page::query()
            ->where('status', ContentStatus::Approved)
            ->whereNotNull('publish_at')
            ->where('publish_at', '<=', now())
            ->orderBy('path')
            ->each(function (Page $page) use (&$count) {
                try {
                    $this->transition($page, WorkflowAction::Publish, null);
                    $count++;
                } catch (ValidationException $e) {
                    $this->logger->log('workflow.schedule_failed', $page, ['errors' => $e->errors()], null);
                }
            });

        return $count;
    }

    private function publish(Page $page, ?User $user): void
    {
        $parent = $page->parent_id ? Page::query()->find($page->parent_id) : null;

        if ($parent !== null && ! $parent->isLive()) {
            throw ValidationException::withMessages([
                'action' => __('Publish the parent page ":title" first.', ['title' => $parent->title]),
            ]);
        }

        $newPath = $parent ? $parent->published_path.'/'.$page->slug : $page->slug;

        $conflict = Page::query()->where('published_path', $newPath)->whereKeyNot($page->getKey())->exists();
        if ($conflict) {
            throw ValidationException::withMessages(['action' => __('Another published page already uses /:path.', ['path' => $newPath])]);
        }

        $oldPath = $page->published_path;

        $page->status = ContentStatus::Published;
        $page->publish_at = null;
        $page->has_unpublished_changes = false;
        $page->published_path = $newPath;
        $page->published_at = now();
        $page->first_published_at ??= now();
        $page->updated_by = $user?->getKey() ?? $page->updated_by;
        $page->save();

        $revision = $this->revisions->record($page, RevisionKind::Published, $user, 'Published');
        $page->published_revision_id = $revision->getKey();
        $page->save();

        if ($oldPath !== null && $oldPath !== $newPath) {
            $this->redirects->recordMove('/'.$oldPath, '/'.$newPath, $user);
            $this->moveLiveDescendants($oldPath, $newPath, $user);
        }

        $this->cache->bump('pages');
    }

    /**
     * When a live parent moves, its live sub-pages move with it (with redirects).
     */
    private function moveLiveDescendants(string $oldPath, string $newPath, ?User $user): void
    {
        Page::query()->live()->where('published_path', 'like', $oldPath.'/%')->each(function (Page $child) use ($oldPath, $newPath, $user) {
            $childOld = $child->published_path;
            $child->published_path = $newPath.substr((string) $childOld, strlen($oldPath));
            $child->saveQuietly();
            $this->redirects->recordMove('/'.$childOld, '/'.$child->published_path, $user);
        });
    }

    private function schedule(Page $page, ?CarbonInterface $publishAt): void
    {
        if ($publishAt === null || $publishAt->isPast()) {
            throw ValidationException::withMessages(['publish_at' => __('Choose a date and time in the future.')]);
        }

        $page->status = ContentStatus::Approved;
        $page->publish_at = Carbon::instance($publishAt);
    }

    private function allowedFromState(Page $page, WorkflowAction $action): bool
    {
        $status = $page->status;

        return match ($action) {
            WorkflowAction::Submit => $status === ContentStatus::Draft,
            WorkflowAction::Approve => $status === ContentStatus::InReview,
            WorkflowAction::RequestChanges => in_array($status, [ContentStatus::InReview, ContentStatus::Approved], true),
            WorkflowAction::Publish, WorkflowAction::Schedule => in_array($status, [ContentStatus::Draft, ContentStatus::InReview, ContentStatus::Approved], true),
            WorkflowAction::Unschedule => $status === ContentStatus::Approved && $page->publish_at !== null,
            WorkflowAction::Unpublish => $page->isLive(),
            WorkflowAction::Archive => $status !== ContentStatus::Archived,
            WorkflowAction::Restore => $status === ContentStatus::Archived,
        };
    }

    private function userMay(Page $page, WorkflowAction $action, User $user): bool
    {
        if (! $user->can($page->contentType().'.'.$action->permission())) {
            return false;
        }

        // Submitting is an edit-level action: only people who may edit the page can submit it.
        return $action !== WorkflowAction::Submit || Gate::forUser($user)->allows('update', $page);
    }
}
