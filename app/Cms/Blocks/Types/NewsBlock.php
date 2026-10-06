<?php

namespace App\Cms\Blocks\Types;

use App\Cms\Blocks\BlockType;
use App\Cms\Fields\Field;

/**
 * News collection: one block type for every presentation (grid, list, carousel…)
 * and both sources (hand-picked static items or the latest published news).
 */
class NewsBlock extends BlockType
{
    public function slug(): string
    {
        return 'news';
    }

    public function label(): string
    {
        return 'News';
    }

    public function category(): string
    {
        return 'dynamic';
    }

    public function icon(): string
    {
        return 'bi-newspaper';
    }

    public function fields(): array
    {
        return [
            Field::text('heading')->label('Heading (optional)'),
            Field::text('empty_text')->label('Text when there is no news')->default('No news yet.'),
            Field::checkbox('show_excerpt')->label('Show summary')->default(true),
            Field::checkbox('show_date')->label('Show date')->default(true),
            Field::repeater('items', [
                Field::text('title')->label('Title')->required(),
                Field::textarea('excerpt')->label('Summary')->max(400),
                Field::image('image')->label('Image'),
                Field::date('date')->label('Date'),
                Field::link('link')->label('Link'),
            ])->label('Items (static mode)')->items(0, 24),
        ];
    }

    public function sourceModes(): array
    {
        return ['dynamic', 'static'];
    }

    public function dynamicEntity(): ?string
    {
        return 'news';
    }

    public function displayModes(): array
    {
        return ['grid', 'list', 'carousel', 'featured'];
    }

    public function defaults(): array
    {
        return [
            'content' => ['heading' => 'Latest news', 'show_excerpt' => true, 'show_date' => true],
            'source' => ['mode' => 'dynamic', 'provider' => 'cms', 'entity' => 'news', 'order' => 'latest', 'limit' => 6],
            'display' => ['mode' => 'grid', 'columns' => ['desktop' => 3, 'tablet' => 2, 'mobile' => 1], 'card_style' => 'elevated', 'image_ratio' => '16:9'],
        ];
    }
}
