<?php

namespace App\Cms\Blocks\Types;

use App\Cms\Blocks\BlockType;
use App\Cms\Fields\Field;

/**
 * Events collection: upcoming events (soonest first) by default; past or all events,
 * a category or featured events through the source filters.
 */
class EventsBlock extends BlockType
{
    public function slug(): string
    {
        return 'events';
    }

    public function label(): string
    {
        return 'Events';
    }

    public function category(): string
    {
        return 'dynamic';
    }

    public function icon(): string
    {
        return 'bi-calendar-event';
    }

    public function fields(): array
    {
        return [
            Field::text('heading')->label('Heading (optional)'),
            Field::text('empty_text')->label('Text when there are no events')->default('No upcoming events right now.'),
            Field::checkbox('show_excerpt')->label('Show summary')->default(true),
            Field::checkbox('show_date')->label('Show date')->default(true),
        ];
    }

    public function sourceModes(): array
    {
        return ['dynamic'];
    }

    public function dynamicEntity(): ?string
    {
        return 'events';
    }

    public function displayModes(): array
    {
        return ['grid', 'list', 'carousel', 'featured'];
    }

    public function defaults(): array
    {
        return [
            'content' => ['heading' => 'Upcoming events', 'empty_text' => 'No upcoming events right now.', 'show_excerpt' => true, 'show_date' => true],
            'source' => ['mode' => 'dynamic', 'provider' => 'cms', 'entity' => 'events', 'order' => 'soonest', 'limit' => 3, 'filters' => ['when' => 'upcoming']],
            'display' => ['mode' => 'grid', 'columns' => ['desktop' => 3, 'tablet' => 2, 'mobile' => 1], 'card_style' => 'elevated', 'image_ratio' => '16:9'],
        ];
    }
}
