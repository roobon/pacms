<?php

namespace App\Models;

/**
 * A program (Phase 8B): objectives and activities (ordered lists), documents, plus
 * everything every content module has (ContentItem). See ProgramType.
 *
 * @property list<array{text: string}>|null $objectives
 * @property list<array{title: string, text: string|null}>|null $activities
 */
class Program extends ContentItem
{
    protected $fillable = [
        'title', 'slug', 'excerpt', 'body', 'featured_media_id', 'featured', 'sidebar_mode', 'sidebar_global_block_id',
        'objectives', 'activities',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['status' => 'draft', 'featured' => false, 'sidebar_mode' => 'default', 'lock_version' => 0];

    public static function typeKey(): string
    {
        return 'programs';
    }

    protected function casts(): array
    {
        return parent::casts() + [
            'objectives' => 'array',
            'activities' => 'array',
        ];
    }
}
