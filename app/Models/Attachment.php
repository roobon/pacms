<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A document (media item) listed on a content item, e.g. a project's reports
 * (DATABASE-ARCHITECTURE.md §7, attachments). Ordered by position.
 *
 * @property int $id
 * @property int $media_id
 * @property string|null $label
 * @property int $position
 */
class Attachment extends Model
{
    protected $fillable = ['media_id', 'label', 'position'];

    /**
     * @return BelongsTo<Media, $this>
     */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }
}
