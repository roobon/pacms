<?php

namespace App\Cms\Blocks\Types;

use App\Cms\Blocks\BlockType;
use App\Cms\Fields\Field;

class SpacerBlock extends BlockType
{
    public const SIZES = ['space.4' => 'Small', 'space.6' => 'Medium', 'space.8' => 'Large', 'space.10' => 'Extra large'];

    public function slug(): string
    {
        return 'spacer';
    }

    public function label(): string
    {
        return 'Spacer';
    }

    public function category(): string
    {
        return 'basic';
    }

    public function icon(): string
    {
        return 'bi-arrows-vertical';
    }

    public function fields(): array
    {
        return [Field::select('size', self::SIZES)->label('Height')->default('space.6')];
    }

    public function defaults(): array
    {
        return ['content' => ['size' => 'space.6']];
    }
}
