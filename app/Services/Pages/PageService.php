<?php

namespace App\Services\Pages;

use App\Cms\Blocks\BlockTreeRepository;
use App\Cms\Blocks\BlockTreeValidator;
use App\Enums\ContentStatus;
use App\Enums\RevisionKind;
use App\Models\Media;
use App\Models\Page;
use App\Models\Revision;
use App\Models\User;
use App\Services\ActivityLog\ActivityLogger;
use App\Services\Content\ContentReferenceService;
use App\Services\Publishing\PublishingService;
use App\Services\Revisions\RevisionService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates, edits and deletes the working copy of pages (fields, SEO and block tree).
 * Publishing is handled by PublishingService; nothing here changes what visitors see.
 */
class PageService
{
    public function __construct(
        private readonly PagePathService $paths,
        private readonly RevisionService $revisions,
        private readonly ContentReferenceService $references,
        private readonly ActivityLogger $logger,
        private readonly PublishingService $publishing,
        private readonly BlockTreeValidator $blockValidator,
        private readonly BlockTreeRepository $blocks,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated input (fields + optional "seo" + optional "blocks" node list)
     */
    public function create(User $user, array $data): Page
    {
        $blocks = array_key_exists('blocks', $data) ? $this->blockValidator->validate((array) $data['blocks'], $user) : null;

        return DB::transaction(function () use ($user, $data, $blocks) {
            $page = new Page;
            $page->fill(Arr::only($data, Page::SNAPSHOT_FIELDS));
            $page->slug = $data['slug'] ?? $this->paths->slugify((string) $data['title']);

            $parent = $page->parent_id ? Page::query()->findOrFail($page->parent_id) : null;
            $this->paths->validate($page, $parent, $page->slug);

            $page->path = $this->paths->pathFor($parent, $page->slug);
            $page->author_id = $user->getKey();
            $page->created_by = $user->getKey();
            $page->updated_by = $user->getKey();
            $page->save();

            $page->saveSeo($data['seo'] ?? null);
            if ($blocks !== null) {
                $this->blocks->save($page, $blocks, $user);
            }
            $this->syncReferences($page);

            $this->revisions->record($page, RevisionKind::Manual, $user, 'Created');
            $this->logger->log('page.created', $page, ['path' => $page->path], $user);

            return $page;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException when the page was changed by someone else meanwhile
     */
    public function update(User $user, Page $page, array $data, int $lockVersion): Page
    {
        // Validate the block tree before taking locks (it may query media and pages).
        $blocks = array_key_exists('blocks', $data) ? $this->blockValidator->validate((array) $data['blocks'], $user) : null;

        return DB::transaction(function () use ($user, $page, $data, $lockVersion, $blocks) {
            $page = Page::query()->lockForUpdate()->findOrFail($page->getKey());

            if ($page->lock_version !== $lockVersion) {
                throw ValidationException::withMessages([
                    'lock_version' => __('This page was changed by someone else while you were editing. Reload it to see the latest version, then apply your changes again.'),
                ]);
            }

            $before = $page->load('seo')->toSnapshot();
            $oldPath = $page->path;

            $page->fill(Arr::only($data, Page::SNAPSHOT_FIELDS));
            // fill() only touches the slug when it was sent: an emptied URL field (null) is
            // regenerated from the title, as on create; an omitted one keeps the current URL.
            $page->slug ??= $this->paths->slugify((string) $page->title);

            $parent = $page->parent_id ? Page::query()->findOrFail($page->parent_id) : null;
            $this->paths->validate($page, $parent, $page->slug);
            $page->path = $this->paths->pathFor($parent, $page->slug);

            if (array_key_exists('seo', $data)) {
                $page->saveSeo($data['seo']);
            }
            if ($blocks !== null) {
                $this->blocks->save($page, $blocks, $user);
            }

            $page->load('seo');
            $after = $page->toSnapshot();

            if ($before === $after && ! $page->isDirty()) {
                return $page;
            }

            $this->markEdited($page, $user);

            if ($oldPath !== $page->path) {
                $this->paths->rebuildDescendants($page);
            }

            $this->syncReferences($page);

            $summary = $this->revisions->summarize($before, $after);
            $this->revisions->record($page, RevisionKind::Manual, $user, $summary);
            $this->logger->log('page.updated', $page, ['summary' => $summary], $user);

            return $page;
        });
    }

    /**
     * Copy a revision back into the working copy as a new draft (history is kept).
     */
    public function restore(User $user, Page $page, Revision $revision): Page
    {
        if ($revision->revisionable_type !== $page->getMorphClass() || (int) $revision->revisionable_id !== (int) $page->getKey()) {
            throw ValidationException::withMessages(['revision' => __('This revision belongs to a different item.')]);
        }

        try {
            $blocks = $this->blockValidator->validate((array) ($revision->snapshot['blocks'] ?? []), $user);
        } catch (ValidationException) {
            throw ValidationException::withMessages(['revision' => __('This revision contains blocks that are no longer valid (for example, deleted images). It cannot be restored automatically.')]);
        }

        return DB::transaction(function () use ($user, $page, $revision, $blocks) {
            $oldPath = $page->path;
            $page->applySnapshot($revision->snapshot);

            $parent = $page->parent_id ? Page::query()->find($page->parent_id) : null;
            $this->paths->validate($page, $parent, (string) $page->slug);
            $page->path = $this->paths->pathFor($parent, (string) $page->slug);

            $this->blocks->save($page, $blocks, $user);
            $this->markEdited($page, $user);

            if ($oldPath !== $page->path) {
                $this->paths->rebuildDescendants($page);
            }

            $page->load('seo');
            $this->syncReferences($page);
            $this->revisions->record($page, RevisionKind::Restore, $user, 'Restored revision #'.$revision->number);
            $this->logger->log('revision.restored', $page, ['revision' => $revision->number], $user);

            return $page;
        });
    }

    public function delete(User $user, Page $page): void
    {
        if ($page->children()->exists()) {
            throw ValidationException::withMessages(['page' => __('Move or delete the sub-pages of ":title" first.', ['title' => $page->title])]);
        }

        DB::transaction(function () use ($user, $page) {
            if ($page->isLive()) {
                $this->publishing->takeOffline($page, ContentStatus::Draft, $user);
            }

            $page->delete();
            $this->references->clear($page);
            $this->logger->log('page.deleted', $page, ['path' => $page->path], $user);
        });
    }

    /**
     * Featured image, social image, and every media item, global block and custom block type used in the page's blocks.
     */
    public function syncReferences(Page $page): void
    {
        $references = [];

        if ($page->featured_media_id && ($media = Media::query()->find($page->featured_media_id))) {
            $references[] = ['target' => $media, 'context' => 'featured_image'];
        }

        $ogImage = $page->seo?->og_image_media_id;
        if ($ogImage && ($media = Media::query()->find($ogImage))) {
            $references[] = ['target' => $media, 'context' => 'og_image'];
        }

        $references = array_merge($references, $this->blocks->references($this->blocks->load($page)));

        $this->references->sync($page, $references);
    }

    /**
     * Any edit invalidates a pending review/approval or schedule: the working copy is a new draft.
     */
    private function markEdited(Page $page, User $user): void
    {
        if (in_array($page->status, [ContentStatus::InReview, ContentStatus::Approved, ContentStatus::Published], true)) {
            $page->status = ContentStatus::Draft;
        }
        $page->publish_at = null;
        $page->has_unpublished_changes = true;
        $page->updated_by = $user->getKey();
        $page->lock_version++;
        $page->save();
    }
}
