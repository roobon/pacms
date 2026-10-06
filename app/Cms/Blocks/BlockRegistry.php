<?php

namespace App\Cms\Blocks;

use App\Models\BlockType as BlockTypeModel;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * All available block types. Core types come from config('pacms.blocks.types');
 * tests and (later) custom block types can register more at runtime.
 */
class BlockRegistry
{
    /** @var array<string, BlockType> */
    private array $types = [];

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
        return isset($this->types[$slug]);
    }

    public function get(string $slug): BlockType
    {
        return $this->types[$slug] ?? throw new InvalidArgumentException("Unknown block type [{$slug}].");
    }

    public function find(string $slug): ?BlockType
    {
        return $this->types[$slug] ?? null;
    }

    /**
     * @return array<string, BlockType>
     */
    public function all(): array
    {
        return $this->types;
    }

    /**
     * Upsert every registered type into block_types (run on each deploy via the seeder).
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

        return compact('created', 'updated');
    }

    /**
     * slug => id, for storing blocks.
     *
     * @return array<string, int>
     */
    public function ids(): array
    {
        return $this->ids ??= BlockTypeModel::query()->pluck('id', 'slug')->all();
    }
}
