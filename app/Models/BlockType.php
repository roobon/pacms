<?php

namespace App\Models;

use App\Cms\Blocks\BlockTreeRepository;
use App\Models\Concerns\HasRevisions;
use App\Models\Contracts\Revisionable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Database record of a block type. Core rows are synchronised from the PHP classes
 * (App\Cms\Blocks\BlockRegistry) and are read-only. Custom rows (is_core = false, slug
 * "custom/…") are created in the admin (CMS-ARCHITECTURE.md §11): their field definitions
 * and structure tree are a staged working copy; pages use the published revision.
 *
 * @property array<int, array<string, mixed>>|null $fields
 * @property Carbon|null $published_at
 */
class BlockType extends Model implements Revisionable
{
    use HasRevisions, SoftDeletes;

    public const CUSTOM_PREFIX = 'custom/';

    public const SNAPSHOT_FIELDS = ['name', 'description', 'icon', 'fields'];

    protected $guarded = ['id'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_core' => true,
        'status' => 'published',
        'version' => 1,
        'has_unpublished_changes' => false,
        'lock_version' => 0,
    ];

    protected function casts(): array
    {
        return [
            'fields' => 'array',
            'capabilities' => 'array',
            'defaults' => 'array',
            'is_core' => 'boolean',
            'has_unpublished_changes' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<BlockType>  $query
     */
    public function scopeCustom(Builder $query): void
    {
        $query->where('is_core', false);
    }

    /**
     * @return BelongsTo<Revision, $this>
     */
    public function publishedRevision(): BelongsTo
    {
        return $this->belongsTo(Revision::class, 'published_revision_id');
    }

    public function isCustom(): bool
    {
        return ! $this->is_core;
    }

    /** The slug without the "custom/" prefix, as edited in the admin. */
    public function shortSlug(): string
    {
        return str_starts_with((string) $this->slug, self::CUSTOM_PREFIX) ? substr((string) $this->slug, strlen(self::CUSTOM_PREFIX)) : (string) $this->slug;
    }

    public function contentType(): string
    {
        return 'block_types';
    }

    public function toSnapshot(): array
    {
        return [
            'schema_version' => '1.0',
            'type' => 'block_type',
            'fields' => $this->only(self::SNAPSHOT_FIELDS) + ['slug' => $this->slug],
            'blocks' => $this->exists ? app(BlockTreeRepository::class)->load($this) : [],
        ];
    }

    public function applySnapshot(array $snapshot): void
    {
        $this->fill(array_intersect_key((array) ($snapshot['fields'] ?? []), array_flip(self::SNAPSHOT_FIELDS)));
    }
}
