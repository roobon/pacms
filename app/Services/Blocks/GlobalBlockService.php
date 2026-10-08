<?php

namespace App\Services\Blocks;

use App\Cms\Blocks\BlockTreeRepository;
use App\Cms\Blocks\BlockTreeValidator;
use App\Enums\RevisionKind;
use App\Models\GlobalBlock;
use App\Models\Revision;
use App\Models\User;
use App\Services\ActivityLog\ActivityLogger;
use App\Services\Cache\CacheVersions;
use App\Services\Content\ContentReferenceService;
use App\Services\Revisions\RevisionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Global blocks (CMS-ARCHITECTURE.md §12): one shared tree placed on many pages through
 * `global-ref` blocks. Staged like pages: saving changes the working copy only;
 * "Publish changes" updates every page that uses the block.
 */
class GlobalBlockService
{
    use ManagesBlockOwners;

    public function __construct(
        protected readonly BlockTreeRepository $blocks,
        protected readonly ContentReferenceService $references,
        private readonly BlockTreeValidator $validator,
        private readonly RevisionService $revisions,
        private readonly ActivityLogger $logger,
        private readonly CacheVersions $cache,
    ) {}

    /**
     * @param  array<string, mixed>  $data  name, slug?, description?, blocks?
     */
    public function create(User $user, array $data): GlobalBlock
    {
        $nodes = $this->validator->validate((array) ($data['blocks'] ?? []), $user, BlockTreeValidator::CONTEXT_GLOBAL);

        return DB::transaction(function () use ($user, $data, $nodes) {
            $global = new GlobalBlock;
            $global->fill(['name' => $data['name'], 'description' => $data['description'] ?? null, 'kind' => $data['kind'] ?? 'generic']);
            $global->slug = $this->uniqueSlug(GlobalBlock::class, (string) ($data['slug'] ?? '') ?: (string) $data['name']);
            $global->created_by = $global->updated_by = $user->id;
            $global->save();

            $this->storeTree($global, $nodes, $user);
            $this->revisions->record($global, RevisionKind::Manual, $user, 'Created');
            $this->logger->log('global_block.created', $global, [], $user);

            return $global;
        });
    }

    /**
     * @param  array<string, mixed>  $data  name, slug?, description?, blocks?
     */
    public function update(User $user, GlobalBlock $global, array $data, int $lockVersion): GlobalBlock
    {
        $nodes = array_key_exists('blocks', $data)
            ? $this->validator->validate((array) $data['blocks'], $user, BlockTreeValidator::CONTEXT_GLOBAL)
            : null;

        return DB::transaction(function () use ($user, $global, $data, $nodes, $lockVersion) {
            $global = $this->lockFresh($global, $lockVersion);
            $before = $global->toSnapshot();

            $global->fill(['name' => $data['name'], 'description' => $data['description'] ?? null, 'kind' => $data['kind'] ?? $global->kind]);
            if (! empty($data['slug']) && $data['slug'] !== $global->slug) {
                $global->slug = $this->uniqueSlug(GlobalBlock::class, (string) $data['slug'], $global->id);
            }
            if ($nodes !== null) {
                $this->storeTree($global, $nodes, $user);
            }

            $global->has_unpublished_changes = true;
            $global->updated_by = $user->id;
            $global->lock_version++;
            $global->save();

            $this->revisions->record($global, RevisionKind::Manual, $user, $this->revisions->summarize($before, $global->toSnapshot()));
            $this->logger->log('global_block.updated', $global, [], $user);

            return $global;
        });
    }

    /**
     * Make the working copy live on every page that uses it.
     */
    public function publish(User $user, GlobalBlock $global): GlobalBlock
    {
        return DB::transaction(function () use ($user, $global) {
            $global = GlobalBlock::query()->lockForUpdate()->findOrFail($global->id);

            $revision = $this->revisions->record($global, RevisionKind::Published, $user, 'Published');
            $global->forceFill([
                'status' => 'published',
                'published_revision_id' => $revision->id,
                'published_at' => now(),
                'has_unpublished_changes' => false,
                'updated_by' => $user->id,
            ])->save();

            $this->cache->bump('globals');
            $this->logger->log('global_block.published', $global, ['revision' => $revision->number], $user);

            return $global;
        });
    }

    /**
     * @throws ValidationException when the block is still placed somewhere
     */
    public function delete(User $user, GlobalBlock $global): void
    {
        $count = $this->references->usagesOf($global)->count();
        if ($count > 0) {
            throw ValidationException::withMessages([
                'global_block' => trans_choice('This global block is used in :count place. Remove it there (or detach it) first.|This global block is used in :count places. Remove it there (or detach it) first.', $count, ['count' => $count]),
            ]);
        }

        DB::transaction(function () use ($user, $global) {
            $global->delete();
            $this->references->clear($global);
            $this->cache->bump('globals');
            $this->logger->log('global_block.deleted', $global, [], $user);
        });
    }

    /**
     * A local copy of the published tree, for replacing a `global-ref` block in the builder
     * ("Detach"). The caller saves it as part of the page; nothing changes here.
     *
     * @return list<array<string, mixed>>
     */
    public function detachedCopy(User $user, GlobalBlock $global): array
    {
        if ($global->published_revision_id === null) {
            throw ValidationException::withMessages(['global_block' => __('Publish this global block before detaching it.')]);
        }

        /** @var Revision $revision */
        $revision = Revision::query()->findOrFail($global->published_revision_id);
        $this->logger->log('global_block.detached', $global, [], $user);

        return array_values((array) ($revision->snapshot['blocks'] ?? []));
    }
}
