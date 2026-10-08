<?php

namespace App\Models;

/**
 * A partner organisation (Phase 8C): name (title), logo (image), description, website,
 * category and display order. Active or inactive; cards link to the partner's website.
 *
 * @property string|null $website_url
 * @property int $position
 */
class Partner extends ContentItem
{
    protected $fillable = [
        'title', 'slug', 'excerpt', 'body', 'featured_media_id', 'featured', 'sidebar_mode', 'sidebar_global_block_id',
        'website_url', 'position',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['status' => 'draft', 'featured' => false, 'sidebar_mode' => 'default', 'position' => 0, 'lock_version' => 0];

    public static function typeKey(): string
    {
        return 'partners';
    }

    protected function casts(): array
    {
        return parent::casts() + ['position' => 'integer'];
    }
}
