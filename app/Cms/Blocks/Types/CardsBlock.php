<?php

namespace App\Cms\Blocks\Types;

use App\Cms\Blocks\BlockType;
use App\Cms\Fields\Field;

class CardsBlock extends BlockType
{
    public function slug(): string
    {
        return 'cards';
    }

    public function label(): string
    {
        return 'Cards';
    }

    public function category(): string
    {
        return 'content';
    }

    public function icon(): string
    {
        return 'bi-grid-3x2-gap';
    }

    public function fields(): array
    {
        return [
            Field::text('heading')->label('Heading (optional)'),
            Field::repeater('items', [
                Field::image('image')->label('Image'),
                Field::icon('icon')->label('Icon'),
                Field::text('title')->label('Title')->required(),
                Field::textarea('text')->label('Text')->max(600),
                Field::link('link')->label('Link'),
            ])->label('Cards')->items(1, 24),
        ];
    }

    public function displayModes(): array
    {
        return ['grid', 'carousel'];
    }

    public function defaults(): array
    {
        return [
            'content' => ['items' => [
                ['title' => 'First card', 'text' => 'A short description.', 'icon' => 'bi-tree'],
                ['title' => 'Second card', 'text' => 'A short description.', 'icon' => 'bi-droplet'],
                ['title' => 'Third card', 'text' => 'A short description.', 'icon' => 'bi-sun'],
            ]],
            'display' => ['mode' => 'grid', 'columns' => ['desktop' => 3, 'tablet' => 2, 'mobile' => 1], 'card_style' => 'elevated'],
        ];
    }
}
