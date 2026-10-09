<?php

namespace App\Cms\Blocks;

use App\Cms\Blocks\Types\ContentTypeBlock;
use App\Models\Block;
use App\Models\BlockType as BlockTypeModel;
use App\Models\GlobalBlock;
use App\Models\Media;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Stores block trees as an adjacency list scoped to an owner (CMS-ARCHITECTURE.md §5.1).
 * A whole tree is loaded with one indexed query and assembled in memory; saving replaces
 * the owner's rows in one transaction (block UUIDs are preserved).
 */
class BlockTreeRepository
{
    private const SECTIONS = ['content', 'source', 'display', 'layout', 'style', 'responsive', 'advanced'];

    public function __construct(private readonly BlockRegistry $registry) {}

    /**
     * The owner's tree in schema node format.
     *
     * @return list<array<string, mixed>>
     */
    public function load(Model $owner): array
    {
        $rows = Block::query()
            ->where('owner_type', $owner->getMorphClass())
            ->where('owner_id', $owner->getKey())
            ->orderBy('position')
            ->get();

        $slugs = array_flip($this->registry->ids());
        $byParent = $rows->groupBy(fn (Block $block) => (string) $block->parent_id);

        $build = function (string $parentKey) use (&$build, $byParent, $slugs): array {
            return ($byParent[$parentKey] ?? collect())->map(function (Block $block) use (&$build, $slugs) {
                $node = ['uuid' => $block->uuid, 'type' => $slugs[$block->block_type_id] ?? 'unknown'];
                if ($block->name) {
                    $node['name'] = $block->name;
                }
                if ($block->is_hidden) {
                    $node['hidden'] = true;
                }
                if ($block->global_block_id) {
                    $node['global_block_id'] = $block->global_block_id;
                }
                foreach (self::SECTIONS as $section) {
                    if (! empty($block->{$section})) {
                        $node[$section] = $block->{$section};
                    }
                }
                $children = $build((string) $block->id);
                if ($children !== []) {
                    $node['children'] = $children;
                }

                return $node;
            })->values()->all();
        };

        return $build('');
    }

    /**
     * Replace the owner's tree with already validated nodes (BlockTreeValidator).
     *
     * @param  list<array<string, mixed>>  $nodes
     */
    public function save(Model $owner, array $nodes, ?User $user = null): void
    {
        $ids = $this->registry->ids();

        DB::transaction(function () use ($owner, $nodes, $user, $ids) {
            Block::query()
                ->where('owner_type', $owner->getMorphClass())
                ->where('owner_id', $owner->getKey())
                ->whereNull('parent_id')
                ->delete(); // children cascade

            $insert = function (array $nodes, ?int $parentId) use (&$insert, $owner, $user, $ids) {
                foreach (array_values($nodes) as $position => $node) {
                    $block = new Block;
                    $block->forceFill([
                        'uuid' => $node['uuid'],
                        'owner_type' => $owner->getMorphClass(),
                        'owner_id' => $owner->getKey(),
                        'parent_id' => $parentId,
                        'position' => $position,
                        'block_type_id' => $ids[$node['type']],
                        'global_block_id' => $node['global_block_id'] ?? null,
                        'name' => $node['name'] ?? null,
                        'is_hidden' => (bool) ($node['hidden'] ?? false),
                        'created_by' => $user?->getKey(),
                        'updated_by' => $user?->getKey(),
                    ] + array_map(fn ($section) => $node[$section] ?? null, array_combine(self::SECTIONS, self::SECTIONS)))->save();

                    if (! empty($node['children'])) {
                        $insert($node['children'], $block->id);
                    }
                }
            };

            $insert($nodes, null);
        });
    }

    /**
     * Every media item referenced anywhere in the tree: [uuid, Media] pairs.
     *
     * @param  list<array<string, mixed>>  $nodes
     * @return list<array{block_uuid: string, media_id: int}>
     */
    public function mediaReferences(array $nodes): array
    {
        $refs = [];

        $walk = function (mixed $value, string $uuid) use (&$walk, &$refs) {
            if (! is_array($value)) {
                return;
            }
            if (isset($value['$media']) && is_numeric($value['$media'])) {
                $refs[$uuid.':'.$value['$media']] = ['block_uuid' => $uuid, 'media_id' => (int) $value['$media']];

                return;
            }
            foreach ($value as $child) {
                $walk($child, $uuid);
            }
        };

        $visit = function (array $nodes) use (&$visit, $walk) {
            foreach ($nodes as $node) {
                foreach (self::SECTIONS as $section) {
                    $walk($node[$section] ?? null, (string) $node['uuid']);
                }
                $visit($node['children'] ?? []);
            }
        };
        $visit($nodes);

        return array_values($refs);
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @return array<int, Media>
     */
    public function referencedMedia(array $nodes): array
    {
        $ids = array_unique(array_column($this->mediaReferences($nodes), 'media_id'));

        return $ids === [] ? [] : Media::query()->whereKey($ids)->get()->keyBy('id')->all();
    }

    /**
     * Everything a tree uses, for content_references ("where is X used?"): media, global
     * blocks, custom block types and the blocks of admin-made content types.
     *
     * @param  list<array<string, mixed>>  $nodes
     * @return list<array{target: Model, context: string, block_uuid: string}>
     */
    public function references(array $nodes): array
    {
        $references = [];

        $media = $this->referencedMedia($nodes);
        foreach ($this->mediaReferences($nodes) as $reference) {
            if (isset($media[$reference['media_id']])) {
                $references[] = ['target' => $media[$reference['media_id']], 'context' => 'block_content', 'block_uuid' => $reference['block_uuid']];
            }
        }

        $globals = [];
        $custom = [];
        $visit = function (array $nodes) use (&$visit, &$globals, &$custom) {
            foreach ($nodes as $node) {
                if (! empty($node['global_block_id'])) {
                    $globals[(string) $node['uuid']] = (int) $node['global_block_id'];
                }
                if (str_starts_with((string) $node['type'], BlockTypeModel::CUSTOM_PREFIX) || str_starts_with((string) $node['type'], ContentTypeBlock::PREFIX)) {
                    $custom[(string) $node['uuid']] = (string) $node['type'];
                }
                $visit($node['children'] ?? []);
            }
        };
        $visit($nodes);

        if ($globals !== []) {
            $models = GlobalBlock::withTrashed()->whereKey(array_unique($globals))->get()->keyBy('id');
            foreach ($globals as $uuid => $id) {
                if (isset($models[$id])) {
                    $references[] = ['target' => $models[$id], 'context' => 'global_ref', 'block_uuid' => $uuid];
                }
            }
        }

        if ($custom !== []) {
            $models = BlockTypeModel::withTrashed()->whereIn('slug', array_unique($custom))->get()->keyBy('slug');
            foreach ($custom as $uuid => $slug) {
                if (isset($models[$slug])) {
                    $context = str_starts_with($slug, ContentTypeBlock::PREFIX) ? 'content_type_block' : 'custom_block';
                    $references[] = ['target' => $models[$slug], 'context' => $context, 'block_uuid' => $uuid];
                }
            }
        }

        return $references;
    }
}
