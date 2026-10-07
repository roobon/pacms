<?php

namespace App\Services\Blocks;

use App\Cms\Blocks\BlockRegistry;
use App\Cms\Blocks\BlockTreeRepository;
use App\Cms\Blocks\BlockTreeValidator;
use App\Cms\Fields\FieldDefinitionValidator;
use App\Enums\RevisionKind;
use App\Models\BlockType;
use App\Models\User;
use App\Services\ActivityLog\ActivityLogger;
use App\Services\Cache\CacheVersions;
use App\Services\Content\ContentReferenceService;
use App\Services\Revisions\RevisionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Custom block types (CMS-ARCHITECTURE.md §11): fields + a bound structure, created in the
 * admin without code. Staged: pages use the published revision; publishing increments
 * `version` and every instance picks up the new structure.
 */
class CustomBlockTypeService
{
    use ManagesBlockOwners;

    public function __construct(
        protected readonly BlockTreeRepository $blocks,
        protected readonly ContentReferenceService $references,
        private readonly BlockTreeValidator $validator,
        private readonly FieldDefinitionValidator $fieldDefinitions,
        private readonly BlockRegistry $registry,
        private readonly RevisionService $revisions,
        private readonly ActivityLogger $logger,
        private readonly CacheVersions $cache,
    ) {}

    /**
     * @param  array<string, mixed>  $data  name, slug?, description?, icon?
     */
    public function create(User $user, array $data): BlockType
    {
        return DB::transaction(function () use ($user, $data) {
            $type = new BlockType;
            $type->forceFill([
                'slug' => $this->uniqueSlug(BlockType::class, (string) ($data['slug'] ?? '') ?: (string) $data['name'], null, BlockType::CUSTOM_PREFIX),
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'icon' => $data['icon'] ?? 'bi-puzzle',
                'category' => 'custom',
                'is_core' => false,
                'status' => 'draft',
                'version' => 0,
                'fields' => [],
                'capabilities' => [],
                'defaults' => [],
                'has_unpublished_changes' => true,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ])->save();

            $this->revisions->record($type, RevisionKind::Manual, $user, 'Created');
            $this->logger->log('block_type.created', $type, ['slug' => $type->slug], $user);

            return $type;
        });
    }

    /**
     * @param  array<string, mixed>  $data  name, description?, icon?, fields (definitions), blocks (structure)
     *
     * @throws ValidationException
     */
    public function update(User $user, BlockType $type, array $data, int $lockVersion): BlockType
    {
        $this->assertCustom($type);

        $fields = $this->fieldDefinitions->validate($data['fields'] ?? []);
        $structure = $this->validator->validate((array) ($data['blocks'] ?? []), $user, BlockTreeValidator::CONTEXT_STRUCTURE, $fields);

        return DB::transaction(function () use ($user, $type, $data, $fields, $structure, $lockVersion) {
            $type = $this->lockFresh($type, $lockVersion);
            $before = $type->toSnapshot();

            $type->forceFill([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'icon' => $data['icon'] ?? $type->icon,
                'fields' => $fields,
                'has_unpublished_changes' => true,
                'updated_by' => $user->id,
                'lock_version' => $type->lock_version + 1,
            ])->save();
            $this->storeTree($type, $structure, $user);

            $this->revisions->record($type, RevisionKind::Manual, $user, $this->revisions->summarize($before, $type->toSnapshot()));
            $this->logger->log('block_type.updated', $type, [], $user);

            return $type;
        });
    }

    /**
     * Publish the working copy: new instances use it and existing ones re-render with it.
     *
     * @throws ValidationException
     */
    public function publish(User $user, BlockType $type): BlockType
    {
        $this->assertCustom($type);

        return DB::transaction(function () use ($user, $type) {
            $type = BlockType::query()->lockForUpdate()->findOrFail($type->id);

            if ($this->blocks->load($type) === []) {
                throw ValidationException::withMessages(['blocks' => __('Build the block’s layout before publishing it.')]);
            }

            $revision = $this->revisions->record($type, RevisionKind::Published, $user, 'Published version '.($type->version + 1));
            $type->forceFill([
                'status' => $type->status === 'disabled' ? 'disabled' : 'published',
                'version' => $type->version + 1,
                'published_revision_id' => $revision->id,
                'published_at' => now(),
                'has_unpublished_changes' => false,
                'updated_by' => $user->id,
            ])->save();

            $this->changed();
            $this->logger->log('block_type.published', $type, ['version' => $type->version], $user);

            return $type;
        });
    }

    /**
     * Disabled types cannot be inserted any more; existing instances keep rendering.
     */
    public function setEnabled(User $user, BlockType $type, bool $enabled): BlockType
    {
        $this->assertCustom($type);

        if ($type->published_revision_id === null) {
            throw ValidationException::withMessages(['status' => __('Publish the block type first.')]);
        }

        $type->forceFill(['status' => $enabled ? 'published' : 'disabled', 'updated_by' => $user->id])->save();
        $this->changed();
        $this->logger->log($enabled ? 'block_type.enabled' : 'block_type.disabled', $type, [], $user);

        return $type;
    }

    /**
     * @throws ValidationException when instances still exist
     */
    public function delete(User $user, BlockType $type): void
    {
        $this->assertCustom($type);

        $count = $this->references->usagesOf($type)->count();
        if ($count > 0) {
            throw ValidationException::withMessages([
                'block_type' => trans_choice('This block type is used in :count place. Remove those blocks first, or disable the type instead.|This block type is used in :count places. Remove those blocks first, or disable the type instead.', $count, ['count' => $count]),
            ]);
        }

        DB::transaction(function () use ($user, $type) {
            $type->delete();
            $this->references->clear($type);
            $this->changed();
            $this->logger->log('block_type.deleted', $type, [], $user);
        });
    }

    private function changed(): void
    {
        $this->registry->forgetCustom();
        $this->cache->bump('block_types');
    }

    private function assertCustom(BlockType $type): void
    {
        abort_if($type->is_core, 403, 'Core block types cannot be changed.');
    }
}
