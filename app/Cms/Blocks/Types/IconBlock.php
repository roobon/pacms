<?php

namespace App\Cms\Blocks\Types;

use App\Cms\Blocks\BlockType;
use App\Cms\Fields\Field;

class IconBlock extends BlockType
{
    public function slug(): string
    {
        return 'icon';
    }

    public function label(): string
    {
        return 'Icon';
    }

    public function category(): string
    {
        return 'basic';
    }

    public function icon(): string
    {
        return 'bi-star';
    }

    public function fields(): array
    {
        return [
            Field::icon('icon')->label('Icon')->required(),
            Field::text('label')->label('Accessible label')->help('Describe the icon for screen readers, or leave empty if it is decorative.')->max(120),
            Field::select('size', ['md' => 'Medium', 'lg' => 'Large', 'xl' => 'Extra large'])->label('Size')->default('lg'),
            Field::color('color')->label('Colour'),
        ];
    }

    public function defaults(): array
    {
        return ['content' => ['icon' => 'bi-tree', 'size' => 'lg']];
    }
}
