<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoMetadata extends Model
{
    protected $table = 'seo_metadata';

    /** Fields that editors can set; everything else falls back to generated defaults. */
    public const FIELDS = ['title', 'description', 'canonical_url', 'robots_index', 'robots_follow', 'og_title', 'og_description', 'og_image_media_id'];

    protected $fillable = self::FIELDS;

    protected function casts(): array
    {
        return [
            'robots_index' => 'boolean',
            'robots_follow' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Media, $this>
     */
    public function ogImage(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'og_image_media_id');
    }
}
