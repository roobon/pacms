<?php

namespace App\Cms\Blocks\Types;

use App\Cms\Blocks\BlockType;
use App\Cms\Fields\Field;

class StatisticsBlock extends BlockType
{
    public function slug(): string
    {
        return 'statistics';
    }

    public function label(): string
    {
        return 'Statistics';
    }

    public function category(): string
    {
        return 'content';
    }

    public function icon(): string
    {
        return 'bi-bar-chart';
    }

    public function fields(): array
    {
        return [
            Field::text('heading')->label('Heading (optional)'),
            Field::repeater('items', [
                Field::number('value')->label('Number')->required()->min(0)->max(1_000_000_000),
                Field::text('prefix')->label('Before the number')->max(8),
                Field::text('suffix')->label('After the number')->max(12),
                Field::text('label')->label('Label')->required()->max(80),
                Field::icon('icon')->label('Icon'),
            ])->label('Statistics')->items(1, 12),
        ];
    }

    public function displayModes(): array
    {
        return ['grid'];
    }

    public function defaults(): array
    {
        return [
            'content' => ['items' => [
                ['value' => 120, 'suffix' => '+', 'label' => 'Schools'],
                ['value' => 15000, 'label' => 'Students'],
                ['value' => 30, 'label' => 'Districts'],
            ]],
            'display' => ['mode' => 'grid', 'columns' => ['desktop' => 3, 'tablet' => 3, 'mobile' => 1]],
        ];
    }
}
