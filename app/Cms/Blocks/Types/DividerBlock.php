<?php

namespace App\Cms\Blocks\Types;

use App\Cms\Blocks\BlockType;
use App\Cms\Fields\Field;

class DividerBlock extends BlockType
{
    public function slug(): string
    {
        return 'divider';
    }

    public function label(): string
    {
        return 'Divider';
    }

    public function category(): string
    {
        return 'basic';
    }

    public function icon(): string
    {
        return 'bi-hr';
    }

    public function fields(): array
    {
        return [Field::select('line', ['solid' => 'Solid', 'dashed' => 'Dashed', 'dotted' => 'Dotted'])->label('Line')->default('solid')];
    }
}
