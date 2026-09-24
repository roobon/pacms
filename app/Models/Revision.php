<?php

namespace App\Models;

use App\Enums\RevisionKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Immutable snapshot of an item (fields + SEO + terms + block tree) in the schema
 * node format (CMS-BLOCK-SCHEMA.md). Written only through RevisionService.
 *
 * @property RevisionKind $kind
 * @property array<string, mixed> $snapshot
 */
class Revision extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'kind' => RevisionKind::class,
            'snapshot' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function revisionable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }
}
