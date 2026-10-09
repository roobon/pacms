<?php

namespace App\Services\Content;

use App\Cms\Blocks\BlockTreeRepository;
use App\Cms\Blocks\BlockTreeValidator;
use App\Cms\Content\ContentType;
use App\Cms\Content\ContentTypeRegistry;
use App\Enums\ContentStatus;
use App\Enums\RevisionKind;
use App\Enums\WorkflowAction;
use App\Models\ContentItem;
use App\Models\ContentRelation;
use App\Models\Gallery;
use App\Models\Media;
use App\Models\Revision;
use App\Models\User;
use App\Services\ActivityLog\ActivityLogger;
use App\Services\Cache\CacheVersions;
use App\Services\Revisions\RevisionService;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Create, edit, publish and restore items of any content module (CMS-ARCHITECTURE.md §3–4,
 * "Direct" publishing): the row is live once published, so changing a published item needs
 * the module's publish permission. Every save records a revision.
 */
class ContentService
{
    public function __construct(
        private readonly BlockTreeValidator $blockValidator,
        private readonly BlockTreeRepository $blocks,
        private readonly ContentReferenceService $references,
        private readonly RevisionService $revisions,
        private readonly ActivityLogger $logger,
        private readonly CacheVersions $cache,
        private readonly ContentTypeRegistry $types,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated: common fields, module fields, terms, seo, blocks
     */
    public function create(ContentType $type, User $user, array $data): ContentItem
    {
        $blocks = array_key_exists('blocks', $data) ? $this->blockValidator->validate((array) $data['blocks'], $user) : null;

        return DB::transaction(function () use ($type, $user, $data, $blocks) {
            $item = $type->newItem();
            $item->forceFill($this->attributes($type, $data));
            $item->slug = $this->uniqueSlug($type, (string) ($data['slug'] ?? '') ?: (string) $data['title']);
            $item->author_id = $user->id;
            $item->created_by = $item->updated_by = $user->id;
            $item->save();

            $this->saveRelations($type, $item, $data, $blocks, $user);
            $this->revisions->record($item, RevisionKind::Manual, $user, 'Created');
            $this->logger->log($type->key().'.created', $item, [], $user);
            $this->changed($type);

            return $item;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException|ValidationException
     */
    public function update(ContentType $type, User $user, ContentItem $item, array $data, int $lockVersion): ContentItem
    {
        // Direct publishing: changing a live item changes the public site.
        if ($item->status === ContentStatus::Published && ! $user->can($type->ability('publish'))) {
            throw new AuthorizationException(__('Only publishers can change published :items.', ['items' => strtolower($type->label())]));
        }

        $blocks = array_key_exists('blocks', $data) ? $this->blockValidator->validate((array) $data['blocks'], $user) : null;

        return DB::transaction(function () use ($type, $user, $item, $data, $blocks, $lockVersion) {
            $item = $item->newQuery()->lockForUpdate()->findOrFail($item->getKey());
            if ((int) $item->getAttribute('lock_version') !== $lockVersion) {
                throw ValidationException::withMessages([
                    'lock_version' => __('This was changed by someone else while you were editing. Reload it to see the latest version, then apply your changes again.'),
                ]);
            }

            $before = $item->load('seo')->toSnapshot();
            $item->forceFill($this->attributes($type, $data));
            if (! empty($data['slug']) && $data['slug'] !== $item->slug) {
                $item->slug = $this->uniqueSlug($type, (string) $data['slug'], $item->getKey());
            }
            $item->updated_by = $user->id;
            $item->setAttribute('lock_version', (int) $item->getAttribute('lock_version') + 1);
            $item->save();

            $this->saveRelations($type, $item, $data, $blocks, $user);
            $item->load('seo');
            $this->revisions->record($item, RevisionKind::Manual, $user, $this->revisions->summarize($before, $item->toSnapshot()));
            $this->logger->log($type->key().'.updated', $item, [], $user);
            $this->changed($type);

            return $item;
        });
    }

    public function delete(ContentType $type, User $user, ContentItem $item): void
    {
        DB::transaction(function () use ($type, $user, $item) {
            $item->delete();
            // Its documents and gallery photos stay in the revisions; the rows would otherwise
            // keep the files from being deleted.
            $item->attachments()->delete();
            if ($item instanceof Gallery) {
                $item->items()->delete();
            }
            ContentRelation::query()->where('owner_type', $item->getMorphClass())->where('owner_id', $item->getKey())->delete();
            $this->references->clear($item);
            $this->logger->log($type->key().'.deleted', $item, [], $user);
        });
        $this->changed($type);
    }

    /**
     * Write a revision back to the item (fields, terms, SEO, blocks) as a new revision.
     */
    public function restore(ContentType $type, User $user, ContentItem $item, Revision $revision): ContentItem
    {
        $blocks = $this->blockValidator->validate((array) ($revision->snapshot['blocks'] ?? []), $user);

        return DB::transaction(function () use ($type, $user, $item, $revision, $blocks) {
            $item->applySnapshot((array) $revision->snapshot);
            $item->updated_by = $user->id;
            $item->setAttribute('lock_version', (int) $item->getAttribute('lock_version') + 1);
            $item->save();
            $this->blocks->save($item, $blocks, $user);
            foreach ($type->relationFields() as $name => $field) {
                if (isset($revision->snapshot['relations'][$name])) {
                    $this->saveRelation($item, $name, $field, (array) $revision->snapshot['relations'][$name]);
                }
            }
            if ($item instanceof Gallery && isset($revision->snapshot['gallery_items'])) {
                $rows = array_filter((array) $revision->snapshot['gallery_items'], fn ($row) => is_array($row) && (empty($row['media_id']) || Media::query()->whereKey((int) $row['media_id'])->exists()));
                $this->saveGalleryItems($item, $rows);
            }
            if ($type->documents() && isset($revision->snapshot['documents'])) {
                // Documents deleted from the library since then are left out.
                $rows = array_filter((array) $revision->snapshot['documents'], fn ($row) => is_array($row) && Media::query()->whereKey((int) ($row['media_id'] ?? 0))->exists());
                $this->saveDocuments($item, $rows);
            }
            $this->syncReferences($item);

            $this->revisions->record($item, RevisionKind::Restore, $user, __('Restored revision #:number', ['number' => $revision->number]));
            $this->logger->log($type->key().'.restored', $item, ['revision' => $revision->number], $user);
            $this->changed($type);

            return $item;
        });
    }

    /**
     * Workflow actions the user may perform now (for the publishing box).
     *
     * @return list<WorkflowAction>
     */
    public function availableActions(ContentType $type, ContentItem $item, User $user): array
    {
        // Managed modules (team, partners) are simply active or inactive.
        $actions = $type->isManaged() ? [WorkflowAction::Publish, WorkflowAction::Unpublish] : WorkflowAction::cases();

        return array_values(array_filter(
            $actions,
            fn (WorkflowAction $action) => $this->allowedFromState($item, $action) && $this->userMay($type, $item, $action, $user),
        ));
    }

    /**
     * @param  array{note?: ?string, publish_at?: ?CarbonInterface}  $options
     *
     * @throws AuthorizationException|ValidationException
     */
    public function transition(ContentType $type, ContentItem $item, WorkflowAction $action, ?User $user, array $options = []): ContentItem
    {
        if ($user !== null && ! $this->userMay($type, $item, $action, $user)) {
            throw new AuthorizationException(__('You are not allowed to :action this :item.', ['action' => strtolower($action->label()), 'item' => $type->singular()]));
        }

        if ($type->isManaged() && ! in_array($action, [WorkflowAction::Publish, WorkflowAction::Unpublish], true)) {
            throw ValidationException::withMessages(['action' => __(':Items are only made active or inactive.', ['items' => $type->label()])]);
        }

        if (! $this->allowedFromState($item, $action)) {
            throw ValidationException::withMessages(['action' => __('":action" is not possible while the :item is :status.', [
                'action' => $action->label(), 'item' => $type->singular(), 'status' => strtolower($item->status->label()),
            ])]);
        }

        $from = $item->status;

        DB::transaction(function () use ($item, $action, $user, $options) {
            match ($action) {
                WorkflowAction::Submit => $item->status = ContentStatus::InReview,
                WorkflowAction::Approve => $item->status = ContentStatus::Approved,
                WorkflowAction::RequestChanges, WorkflowAction::Restore => $item->status = ContentStatus::Draft,
                WorkflowAction::Publish => $this->publish($item),
                WorkflowAction::Schedule => $this->schedule($item, $options['publish_at'] ?? null),
                WorkflowAction::Unschedule => $item->publish_at = null,
                WorkflowAction::Unpublish => [$item->status, $item->publish_at] = [ContentStatus::Draft, null],
                WorkflowAction::Archive => [$item->status, $item->publish_at] = [ContentStatus::Archived, null],
            };
            $item->updated_by = $user?->getKey() ?? $item->updated_by;
            $item->save();

            if ($action === WorkflowAction::Publish) {
                $this->revisions->record($item, RevisionKind::Published, $user, 'Published');
            }
        });

        $this->logger->log('workflow.'.$action->value, $item, array_filter([
            'from' => $from->value,
            'to' => $item->status->value,
            'note' => isset($options['note']) ? mb_substr((string) $options['note'], 0, 1000) : null,
            'publish_at' => $item->publish_at?->toIso8601String(),
        ]), $user);
        $this->changed($type);

        return $item;
    }

    /**
     * Publish approved items whose scheduled time has passed (every content module).
     */
    public function publishDue(ContentType $type): int
    {
        $count = 0;

        $type->query()
            ->where('status', ContentStatus::Approved)
            ->whereNotNull('publish_at')
            ->where('publish_at', '<=', now())
            ->orderBy('publish_at')
            ->each(function (ContentItem $item) use ($type, &$count) {
                $this->transition($type, $item, WorkflowAction::Publish, null);
                $count++;
            });

        return $count;
    }

    public function syncReferences(ContentItem $item): void
    {
        $references = [];
        if ($item->featured_media_id && ($media = Media::query()->find($item->featured_media_id))) {
            $references[] = ['target' => $media, 'context' => 'featured_image'];
        }
        // Documents and other media fields: a used file cannot be deleted or made private.
        foreach ($item->type()->fields() as $name => $field) {
            $id = $field['type'] === 'media' ? $item->getAttribute($name) : null;
            if ($id && ($media = Media::query()->find((int) $id))) {
                $references[] = ['target' => $media, 'context' => $name];
            }
        }
        if ($item->type()->documents()) {
            foreach ($item->attachments()->with('media')->get() as $attachment) {
                if ($attachment->media !== null) {
                    $references[] = ['target' => $attachment->media, 'context' => 'document'];
                }
            }
        }
        if ($item instanceof Gallery) {
            foreach ($item->items()->with('media')->get() as $galleryItem) {
                if ($galleryItem->media !== null) {
                    $references[] = ['target' => $galleryItem->media, 'context' => 'gallery_item'];
                }
            }
        }
        $og = $item->seo?->og_image_media_id;
        if ($og && ($media = Media::query()->find($og))) {
            $references[] = ['target' => $media, 'context' => 'og_image'];
        }

        $this->references->sync($item, array_merge($references, $this->blocks->references($this->blocks->load($item))));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(ContentType $type, array $data): array
    {
        $common = Arr::only($data, ['title', 'excerpt', 'body', 'featured_media_id', 'featured', 'sidebar_mode', 'sidebar_global_block_id']);
        if (($common['sidebar_mode'] ?? 'default') !== 'custom') {
            $common['sidebar_global_block_id'] = null;
        }

        return $common + $type->prepare(Arr::only($data, array_keys($type->columnFields())));
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>|null  $blocks
     */
    private function saveRelations(ContentType $type, ContentItem $item, array $data, ?array $blocks, User $user): void
    {
        if ($type->taxonomy() !== null && array_key_exists('terms', $data)) {
            $item->syncTerms((string) $type->taxonomy(), array_map('intval', (array) $data['terms']));
        }
        if (array_key_exists('seo', $data)) {
            $item->saveSeo((array) $data['seo']);
        }
        if ($blocks !== null) {
            $this->blocks->save($item, $blocks, $user);
        }
        if ($type->documents() && array_key_exists('documents', $data)) {
            $this->saveDocuments($item, (array) $data['documents']);
        }
        foreach ($type->relationFields() as $name => $field) {
            if (array_key_exists($name, $data)) {
                $this->saveRelation($item, $name, $field, (array) ($data[$name] ?? []));
            }
        }
        if ($item instanceof Gallery && array_key_exists('gallery_items', $data)) {
            $this->saveGalleryItems($item, (array) $data['gallery_items']);
        }
        $this->syncReferences($item);
    }

    /**
     * @param  array<string, mixed>  $field
     * @param  array<int|string, mixed>  $ids
     */
    private function saveRelation(ContentItem $item, string $name, array $field, array $ids): void
    {
        $target = $this->types->get((string) $field['target']);
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (empty($field['multiple'])) {
            $ids = array_slice($ids, 0, 1);
        }
        // Only items that exist (deleted ones are dropped); order as chosen.
        $existing = $target->query()->whereKey($ids)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $item->syncRelated($name, $target->newItem()->getMorphClass(), array_values(array_filter($ids, fn (int $id) => in_array($id, $existing, true))));
    }

    /**
     * Replace a gallery's photos and videos (rows: media_id or video_url, caption, alt_override, credit).
     *
     * @param  array<int|string, mixed>  $rows
     */
    private function saveGalleryItems(Gallery $gallery, array $rows): void
    {
        $gallery->items()->delete();
        $position = 0;
        ksort($rows); // validated() can return rows out of their submitted order
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $mediaId = empty($row['media_id']) ? null : (int) $row['media_id'];
            $videoUrl = $mediaId === null && ! empty($row['video_url']) ? mb_substr(trim((string) $row['video_url']), 0, 1024) : null;
            if ($mediaId === null && $videoUrl === null) {
                continue;
            }
            $text = fn (string $key, int $max) => ($value = trim((string) ($row[$key] ?? ''))) === '' ? null : mb_substr($value, 0, $max);
            $gallery->items()->create([
                'media_id' => $mediaId,
                'video_url' => $videoUrl,
                'caption' => $text('caption', 1000),
                'alt_override' => $text('alt_override', 255),
                'credit' => $text('credit', 191),
                'position' => $position++,
            ]);
        }
    }

    /**
     * Replace the item's document list (rows: media_id, label), keeping the given order.
     *
     * @param  array<int|string, mixed>  $rows
     */
    private function saveDocuments(ContentItem $item, array $rows): void
    {
        $item->attachments()->delete();
        $position = 0;
        ksort($rows); // validated() can return rows out of their submitted order
        foreach ($rows as $row) {
            if (! is_array($row) || empty($row['media_id'])) {
                continue;
            }
            $label = trim((string) ($row['label'] ?? ''));
            $item->attachments()->create(['media_id' => (int) $row['media_id'], 'label' => $label === '' ? null : mb_substr($label, 0, 255), 'position' => $position++]);
        }
    }

    private function publish(ContentItem $item): void
    {
        $item->status = ContentStatus::Published;
        $item->publish_at = null;
        // The first publication date is kept when an item is re-published.
        $item->published_at ??= now();
        if ($item->published_at->isFuture()) {
            $item->published_at = now();
        }
    }

    private function schedule(ContentItem $item, ?CarbonInterface $publishAt): void
    {
        if ($publishAt === null || $publishAt->isPast()) {
            throw ValidationException::withMessages(['publish_at' => __('Choose a date and time in the future.')]);
        }

        $item->status = ContentStatus::Approved;
        $item->publish_at = Carbon::instance($publishAt);
    }

    private function allowedFromState(ContentItem $item, WorkflowAction $action): bool
    {
        $status = $item->status;

        return match ($action) {
            WorkflowAction::Submit => $status === ContentStatus::Draft,
            WorkflowAction::Approve => $status === ContentStatus::InReview,
            WorkflowAction::RequestChanges => in_array($status, [ContentStatus::InReview, ContentStatus::Approved], true),
            WorkflowAction::Publish, WorkflowAction::Schedule => in_array($status, [ContentStatus::Draft, ContentStatus::InReview, ContentStatus::Approved], true),
            WorkflowAction::Unschedule => $status === ContentStatus::Approved && $item->publish_at !== null,
            WorkflowAction::Unpublish => $status === ContentStatus::Published,
            WorkflowAction::Archive => $status !== ContentStatus::Archived,
            WorkflowAction::Restore => $status === ContentStatus::Archived,
        };
    }

    private function userMay(ContentType $type, ContentItem $item, WorkflowAction $action, User $user): bool
    {
        if (! $user->can($type->ability($action->permission()))) {
            return false;
        }

        return $action !== WorkflowAction::Submit || Gate::forUser($user)->allows('update', $item);
    }

    private function uniqueSlug(ContentType $type, string $wanted, ?int $ignoreId = null): string
    {
        $base = Str::limit(Str::slug($wanted) ?: $type->routePrefix().'-item', 180, '');
        $candidate = $base;
        $n = 2;

        // Slugs are unique per type (deleted items keep theirs, so restored links still work).
        while ($type->query()->withTrashed()->where('slug', $candidate)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $candidate = "{$base}-{$n}";
            $n++;
        }

        return $candidate;
    }

    private function changed(ContentType $type): void
    {
        // Pages show module items in blocks and sidebars; their cached payloads depend on this group.
        $this->cache->bump($type->key());
    }
}
