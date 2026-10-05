<?php

namespace App\Cms\Blocks\Types;

use App\Cms\Blocks\BlockType;
use App\Cms\Fields\Field;

class RichTextBlock extends BlockType
{
    public function slug(): string
    {
        return 'rich-text';
    }

    public function label(): string
    {
        return 'Text';
    }

    public function category(): string
    {
        return 'basic';
    }

    public function icon(): string
    {
        return 'bi-text-paragraph';
    }

    public function fields(): array
    {
        return [Field::richText('html')->label('Text')->required()];
    }

    public function defaults(): array
    {
        return ['content' => ['html' => '<p>Write something…</p>']];
    }
}
