<?php

namespace App\Cms\Blocks\Types;

/**
 * Events collection: upcoming events (soonest first) by default; past or all events,
 * a category or featured events through the source filters.
 */
class EventsBlock extends ContentCollectionBlock
{
    protected function entity(): string
    {
        return 'events';
    }

    protected function emptyText(): string
    {
        return 'No upcoming events right now.';
    }

    protected function sourceDefaults(): array
    {
        return ['order' => 'soonest', 'limit' => 3, 'filters' => ['when' => 'upcoming']];
    }

    public function label(): string
    {
        return 'Events';
    }

    public function icon(): string
    {
        return 'bi-calendar-event';
    }

    public function defaults(): array
    {
        $defaults = parent::defaults();
        $defaults['content']['heading'] = 'Upcoming events';

        return $defaults;
    }
}
