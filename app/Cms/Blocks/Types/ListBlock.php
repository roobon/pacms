<?php

namespace App\Cms\Blocks\Types;

use App\Cms\Blocks\BlockType;
use App\Cms\Fields\Field;

/**
 * A list of short items (features, steps, contacts): bullets, numbers, check marks or an
 * icon per item, optionally linked. Longer formatted lists belong in a Text block.
 */
class ListBlock extends BlockType
{
    public const STYLES = ['none' => 'None (plain lines)', 'bullet' => 'Bullets', 'number' => 'Numbers', 'check' => 'Check marks', 'icon' => 'Icons'];

    public function slug(): string
    {
        return 'list';
    }

    public function label(): string
    {
        return 'List';
    }

    public function description(): string
    {
        return 'Short items: plain, or with bullets, numbers, check marks or icons.';
    }

    public function category(): string
    {
        return 'basic';
    }

    public function icon(): string
    {
        return 'bi-list-check';
    }

    public function fields(): array
    {
        return [
            Field::select('style', self::STYLES)->label('Style')->default('check'),
            Field::icon('icon')->label('Icon (for “Icons”)')->help('Used for items without their own icon.')->default('bi-arrow-right-circle'),
            Field::select('columns', ['1' => 'One column', '2' => 'Two columns', '3' => 'Three columns'])->label('Columns (desktop)')->default('1'),
            Field::select('item_padding', ['none' => 'None', 'sm' => 'Small', 'md' => 'Medium', 'lg' => 'Large'])->label('Item padding')->default('none')
                ->help('Space inside each item. Space between items is under Layout.'),
            Field::checkbox('dividers')->label('Lines between items'),
            Field::repeater('items', [
                Field::text('text')->label('Text')->required()->max(300),
                Field::icon('icon')->label('Icon (optional)'),
                Field::link('link')->label('Link (optional)'),
            ])->label('Items')->items(1, 50),
        ];
    }

    public function defaults(): array
    {
        return [
            'content' => ['items' => [['text' => 'First item'], ['text' => 'Second item'], ['text' => 'Third item']]],
        ];
    }
}
