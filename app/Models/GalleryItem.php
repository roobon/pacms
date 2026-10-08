<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One photo (library image) or video (YouTube/Vimeo link) in a gallery, in order.
 *
 * @property int $id
 * @property int $gallery_id
 * @property int|null $media_id
 * @property string|null $video_url
 * @property string|null $caption
 * @property string|null $alt_override
 * @property string|null $credit
 * @property int $position
 */
class GalleryItem extends Model
{
    protected $fillable = ['media_id', 'video_url', 'caption', 'alt_override', 'credit', 'position'];

    /**
     * @return BelongsTo<Media, $this>
     */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }
}
