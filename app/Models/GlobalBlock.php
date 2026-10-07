<?php

namespace App\Models;

use App\Cms\Blocks\BlockTreeRepository;
use App\Models\Concerns\HasRevisions;
use App\Models\Contracts\Revisionable;
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

    /** Kinds offered in the admin now; header/footer arrive with navigation (Phase 9). */
    public const KINDS = ['generic' => 'Reusable section'];

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
