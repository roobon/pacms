<?php

namespace App\Cms\Blocks\Types;

use App\Cms\Blocks\BlockType;
use App\Cms\Fields\Field;

class HeadingBlock extends BlockType
{
    public function slug(): string
    {
        return 'heading';
    }

    public function label(): string
    {
        return 'Heading';
    }

    public function category(): string
    {
        return 'basic';
    }

    public function icon(): string
    {
        return 'bi-type-h1';
    }

    public function fields(): array
    {
        return [
            Field::text('eyebrow')->label('Small text above')->max(120),
            Field::text('text')->label('Heading')->required(),
            Field::select('level', ['1' => 'H1 — page title', '2' => 'H2', '3' => 'H3', '4' => 'H4', '5' => 'H5', '6' => 'H6'])
                ->label('Level')->default('2')->help('Use one H1 per page and do not skip levels.'),
        ];
    }

    public function defaults(): array
    {
        return ['content' => ['text' => 'New heading', 'level' => '2']];
    }
}
