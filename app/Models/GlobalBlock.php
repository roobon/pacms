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
 * One shared block tree placed on many pages through `global-ref` blocks
 * (CMS-ARCHITECTURE.md §12). Staged: pages render the published snapshot, so editing the
 * working copy changes nothing until "Publish changes".
 *
 * @property Carbon|null $published_at
 */
class GlobalBlock extends Model implements Revisionable
{
    use HasRevisions, SoftDeletes;

    public const SNAPSHOT_FIELDS = ['name', 'slug', 'kind', 'description'];

    /** Sidebars: next to module items (Phase 8). Header and footer: the site's chrome (Phase 9). */
    public const KINDS = ['generic' => 'Reusable section', 'sidebar' => 'Sidebar', 'header' => 'Header', 'footer' => 'Footer'];

    protected $fillable = ['name', 'slug', 'kind', 'description'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'kind' => 'generic',
        'status' => 'draft',
        'has_unpublished_changes' => true,
        'lock_version' => 0,
    ];

    protected function casts(): array
    {
        return [
            'has_unpublished_changes' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function contentType(): string
    {
        return 'global_blocks';
    }

    /**
     * @return BelongsTo<Revision, $this>
     */
    public function publishedRevision(): BelongsTo
    {
        return $this->belongsTo(Revision::class, 'published_revision_id');
    }

    /**
     * Global blocks that can be shown as a module sidebar: any published one, with the
     * "Sidebar" kind listed first (Settings → Sidebars, and per item).
     *
     * @param  Builder<GlobalBlock>  $query
     */
    public function scopeSidebarChoices(Builder $query): void
    {
        $query->whereNotNull('published_revision_id')
            ->orderByRaw("CASE WHEN kind = 'sidebar' THEN 0 ELSE 1 END")
            ->orderBy('name');
    }

    public function isPublished(): bool
    {
        return $this->published_revision_id !== null && ! $this->trashed();
    }

    public function toSnapshot(): array
    {
        return [
            'schema_version' => '1.0',
            'type' => 'global_block',
            'fields' => $this->only(self::SNAPSHOT_FIELDS),
            'blocks' => $this->exists ? app(BlockTreeRepository::class)->load($this) : [],
        ];
    }

    public function applySnapshot(array $snapshot): void
    {
        $this->fill(array_intersect_key((array) ($snapshot['fields'] ?? []), array_flip(self::SNAPSHOT_FIELDS)));
    }
}
