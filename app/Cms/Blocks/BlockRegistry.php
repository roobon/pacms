<?php

namespace App\Cms\Blocks;

use App\Models\BlockType as BlockTypeModel;
use App\Models\Revision;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * All available block types. Core types come from config('pacms.blocks.types'); tests can
 * register more at runtime. Custom block types (CMS-ARCHITECTURE.md §11) are loaded from
 * their published revisions on first use.
 */
class BlockRegistry
{
    /** @var array<string, BlockType> */
    private array $types = [];

    /** @var array<string, CustomBlockType>|null */
    private ?array $custom = null;

    /** @var array<string, int>|null */
    private ?array $ids = null;

    public function __construct()
    {
        foreach (config('pacms.blocks.types', []) as $class) {
            $this->register(app($class));
        }
    }

    public function register(BlockType $type): void
    {
        $this->types[$type->slug()] = $type;
    }

    public function has(string $slug): bool
    {
        return $this->find($slug) !== null;
    }

    public function get(string $slug): BlockType
    {
        return $this->find($slug) ?? throw new InvalidArgumentException("Unknown block type [{$slug}].");
    }

    public function find(string $slug): ?BlockType
    {
        if (isset($this->types[$slug])) {
            return $this->types[$slug];
        }

        return str_starts_with($slug, BlockTypeModel::CUSTOM_PREFIX) ? ($this->customTypes()[$slug] ?? null) : null;
    }

    /**
     * Core types and published custom types.
     *
     * @return array<string, BlockType>
     */
    public function all(): array
    {
        return $this->types + $this->customTypes();
    }

    /**
     * Core (PHP) types only.
     *
     * @return array<string, BlockType>
     */
    public function core(): array
    {
        return $this->types;
    }

    /**
     * Published custom types (disabled ones still render but are not insertable).
     *
     * @return array<string, CustomBlockType>
     */
    public function customTypes(): array
    {
        if ($this->custom !== null) {
            return $this->custom;
        }

        $rows = BlockTypeModel::query()->custom()->whereNotNull('published_revision_id')->get();
        $revisions = Revision::query()->whereKey($rows->pluck('published_revision_id'))->get()->keyBy('id');

        $this->custom = [];
        foreach ($rows as $row) {
            $snapshot = (array) ($revisions[$row->published_revision_id]->snapshot ?? []);
            $fields = (array) ($snapshot['fields'] ?? []);

            $this->custom[$row->slug] = new CustomBlockType(
                id: $row->id,
                slug: $row->slug,
                name: (string) ($fields['name'] ?? $row->name),
                icon: (string) ($fields['icon'] ?? $row->icon),
                about: (string) ($fields['description'] ?? ''),
                fieldDefinitions: array_values((array) ($fields['fields'] ?? [])),
                structure: array_values((array) ($snapshot['blocks'] ?? [])),
                version: (int) $row->version,
                insertable: $row->status === 'published',
            );
        }

        return $this->custom;
    }

    /** Call after a custom type is published, disabled or deleted. */
    public function forgetCustom(): void
    {
        $this->custom = null;
        $this->ids = null;
    }

    /**
     * Upsert every core type into block_types (run on each deploy via the seeder).
     *
     * @return array{created: int, updated: int}
     */
    public function sync(): array
    {
        $created = $updated = 0;

        DB::transaction(function () use (&$created, &$updated) {
            foreach ($this->types as $type) {
                $data = $type->toArray();
                $model = BlockTypeModel::query()->firstOrNew(['slug' => $data['slug']]);
                $model->exists ? $updated++ : $created++;

                $model->forceFill([
                    'name' => $data['label'],
                    'description' => $data['description'] ?: null,
                    'category' => $data['category'],
                    'icon' => $data['icon'],
                    'is_core' => true,
                    'status' => 'published',
                    'fields' => $data['fields'],
                    'capabilities' => $data['capabilities'],
                    'defaults' => $data['defaults'],
                ])->save();
            }
        });

        $this->ids = null;

        return compact('created', 'updated');
    }

    /**
     * slug => id, for storing blocks (core and custom, including deleted custom types so
     * old revisions can still be loaded).
     *
     * @return array<string, int>
     */
    public function ids(): array
    {
        return $this->ids ??= BlockTypeModel::withTrashed()->pluck('id', 'slug')->all();
    }
}
