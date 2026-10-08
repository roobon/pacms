<?php

namespace App\Models;

use Illuminate\Support\Carbon;

/**
 * A project (Phase 8B): status, dates, location, manager and website, documents, plus
 * everything every content module has (ContentItem). See ProjectType.
 *
 * @property string $project_status
 * @property Carbon|null $start_date
 * @property Carbon|null $end_date
 * @property string|null $location
 * @property string|null $manager_name
 * @property string|null $website_url
 */
class Project extends ContentItem
{
    protected $fillable = [
        'title', 'slug', 'excerpt', 'body', 'featured_media_id', 'featured', 'sidebar_mode', 'sidebar_global_block_id',
        'project_status', 'start_date', 'end_date', 'location', 'manager_name', 'website_url',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['status' => 'draft', 'featured' => false, 'sidebar_mode' => 'default', 'project_status' => 'ongoing', 'lock_version' => 0];

    public static function typeKey(): string
    {
        return 'projects';
    }

    protected function casts(): array
    {
        return parent::casts() + [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }
}
