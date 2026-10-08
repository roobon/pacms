<?php

namespace App\Models;

use Illuminate\Support\Carbon;

/**
 * An event (Phase 8): a date range, place and registration link, plus everything every
 * content module has (ContentItem). Listed as upcoming/past; see EventType.
 *
 * @property Carbon $start_at
 * @property Carbon|null $end_at
 */
class Event extends ContentItem
{
    protected $fillable = [
        'title', 'slug', 'excerpt', 'body', 'featured_media_id', 'featured', 'sidebar_mode', 'sidebar_global_block_id',
        'start_at', 'end_at', 'all_day', 'timezone', 'venue', 'address', 'map_url', 'registration_url', 'organizer',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['status' => 'draft', 'featured' => false, 'sidebar_mode' => 'default', 'all_day' => false, 'lock_version' => 0];

    public static function typeKey(): string
    {
        return 'events';
    }

    protected function casts(): array
    {
        return parent::casts() + [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'all_day' => 'boolean',
        ];
    }

    /** An event is upcoming until it has ended (or, without an end, until its start day is over). */
    public function isUpcoming(): bool
    {
        $end = $this->end_at ?? $this->start_at->copy()->endOfDay();

        return $end->isFuture();
    }
}
