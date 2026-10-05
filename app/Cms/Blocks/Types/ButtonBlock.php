<?php

namespace App\Cms\Blocks\Types;

use App\Cms\Blocks\BlockType;
use App\Cms\Fields\Field;

class ButtonBlock extends BlockType
{
    public const VARIANTS = ['primary' => 'Primary', 'secondary' => 'Secondary', 'accent' => 'Accent', 'outline' => 'Outline', 'link' => 'Text link'];

    public function slug(): string
    {
        return 'button';
    }

    public function label(): string
    {
        return 'Button';
    }

    public function category(): string
    {
        return 'basic';
    }

    public function icon(): string
    {
        return 'bi-hand-index';
    }

    public function fields(): array
    {
        return [
            Field::text('label')->label('Label')->required()->max(80),
            Field::link('link')->label('Link')->required(),
            Field::select('variant', self::VARIANTS)->label('Style')->default('primary'),
            Field::icon('icon')->label('Icon (optional)'),
        ];
    }

    public function defaults(): array
    {
        return ['content' => ['label' => 'Learn more', 'variant' => 'primary', 'link' => ['type' => 'url', 'url' => '/']]];
    }
}
