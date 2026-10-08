<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A gallery (Phase 8C, CMS mode): ordered photos and videos with captions, a cover (the
 * image), date, location and credit, and links to an event, project or program.
 * External providers (Facebook, Flickr…) arrive in Phase 10.
 *
 * @property string $gallery_type
 * @property Carbon|null $gallery_date
 * @property string|null $location
 * @property string|null $credit
 */
class Gallery extends ContentItem
{
    protected $fillable = [
        'title', 'slug', 'excerpt', 'body', 'featured_media_id', 'featured', 'sidebar_mode', 'sidebar_global_block_id',
        'gallery_type', 'gallery_date', 'location', 'credit',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['status' => 'draft', 'featured' => false, 'sidebar_mode' => 'default', 'gallery_type' => 'photo', 'lock_version' => 0];

    public static function typeKey(): string
    {
        return 'galleries';
    }

    protected function casts(): array
    {
        return parent::casts() + ['gallery_date' => 'date'];
    }

    /**
     * @return HasMany<GalleryItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(GalleryItem::class)->orderBy('position');
    }
}
