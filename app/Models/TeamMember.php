<?php

namespace App\Models;

/**
 * A team member (Phase 8C): name (title), designation, photo, biography, contact details
 * shown only when chosen, social links and a display order. Active or inactive.
 *
 * @property string|null $designation
 * @property string|null $email
 * @property bool $show_email
 * @property string|null $phone
 * @property bool $show_phone
 * @property list<array{network: string|null, url: string|null}>|null $social_links
 * @property int $position
 */
class TeamMember extends ContentItem
{
    protected $fillable = [
        'title', 'slug', 'excerpt', 'body', 'featured_media_id', 'featured', 'sidebar_mode', 'sidebar_global_block_id',
        'designation', 'email', 'show_email', 'phone', 'show_phone', 'social_links', 'position',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['status' => 'draft', 'featured' => false, 'sidebar_mode' => 'default', 'show_email' => false, 'show_phone' => false, 'position' => 0, 'lock_version' => 0];

    public static function typeKey(): string
    {
        return 'team';
    }

    protected function casts(): array
    {
        return parent::casts() + [
            'show_email' => 'boolean',
            'show_phone' => 'boolean',
            'social_links' => 'array',
            'position' => 'integer',
        ];
    }
}
