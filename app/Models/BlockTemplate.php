<?php

namespace App\Models;

use App\Cms\Blocks\BlockTreeRepository;
use App\Models\Concerns\HasRevisions;
use App\Models\Contracts\Revisionable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A saved block tree (one block, a section or a whole page) that editors insert as an
 * independent deep copy (CMS-ARCHITECTURE.md §12). Inserting never links back to it.
 * Every save is a revision; templates are not staged because nothing renders them live.
 */
class BlockTemplate extends Model implements Revisionable
{
    use HasRevisions, SoftDeletes;

    public const SNAPSHOT_FIELDS = ['name', 'slug', 'description', 'scope', 'category', 'status'];

    public const SCOPES = ['block' => 'Single block', 'section' => 'Section', 'page' => 'Whole page'];

    protected $fillable = ['name', 'slug', 'description', 'scope', 'category', 'status'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'scope' => 'section',
        'status' => 'published',
        'is_system' => false,
        'lock_version' => 0,
    ];

    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }

    public function contentType(): string
    {
        return 'block_templates';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function isAvailable(): bool
    {
        return $this->status === 'published' && ! $this->trashed();
    }

    public function toSnapshot(): array
    {
        return [
            'schema_version' => '1.0',
            'type' => 'block_template',
            'fields' => $this->only(self::SNAPSHOT_FIELDS),
            'blocks' => $this->exists ? app(BlockTreeRepository::class)->load($this) : [],
        ];
    }

    public function applySnapshot(array $snapshot): void
    {
        $this->fill(array_intersect_key((array) ($snapshot['fields'] ?? []), array_flip(self::SNAPSHOT_FIELDS)));
    }
}
