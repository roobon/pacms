<?php

namespace App\Cms\Blocks\Types;

use App\Cms\Blocks\BlockType;
use App\Cms\Fields\Field;

/**
 * Items of an external feed (Design → External sources), read from local storage: JSON
 * Feed, RSS or Atom of another site, or a YouTube channel (CMS-ARCHITECTURE.md §16.2).
 */
class FeedBlock extends BlockType
{
    public function slug(): string
    {
        return 'feed';
    }

    public function label(): string
    {
        return 'Feed';
    }

    public function category(): string
    {
        return 'external';
    }

    public function icon(): string
    {
        return 'bi-rss';
    }

    public function fields(): array
    {
        return [
            Field::text('heading')->label('Heading (optional)'),
            Field::text('empty_text')->label('Text when there is nothing to show')->default('Nothing to show yet.'),
            Field::checkbox('show_excerpt')->label('Show summary')->default(true),
            Field::checkbox('show_date')->label('Show date')->default(true),
            Field::checkbox('show_source')->label('Show the source name')->default(true),
        ];
    }

    public function sourceModes(): array
    {
        return ['external'];
    }

    public function externalProviders(): array
    {
        return ['feed'];
    }

    public function displayModes(): array
    {
        return ['grid', 'list', 'carousel', 'featured'];
    }

    public function defaults(): array
    {
        return [
            'content' => ['heading' => 'From our partners', 'empty_text' => 'Nothing to show yet.', 'show_excerpt' => true, 'show_date' => true, 'show_source' => true],
            'source' => ['mode' => 'external', 'provider' => 'feed', 'source' => '', 'limit' => 6],
            'display' => ['mode' => 'grid', 'columns' => ['desktop' => 3, 'tablet' => 2, 'mobile' => 1], 'card_style' => 'elevated', 'image_ratio' => '16:9'],
        ];
    }
}
