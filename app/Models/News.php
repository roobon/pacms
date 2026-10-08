<?php

namespace App\Models;

/**
 * A news article (full module, Phase 8). Direct publishing with workflow, scheduling,
 * revisions, builder content and a sidebar; see ContentItem and NewsType.
 */
class News extends ContentItem
{
    protected $table = 'news';

    protected $fillable = ['title', 'slug', 'excerpt', 'body', 'featured_media_id', 'featured', 'sidebar_mode', 'sidebar_global_block_id'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['status' => 'draft', 'featured' => false, 'sidebar_mode' => 'default', 'lock_version' => 0];

    public static function typeKey(): string
    {
        return 'news';
    }
}
