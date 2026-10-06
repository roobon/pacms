<?php

namespace App\Cms\Blocks\Types;

use App\Cms\Blocks\BlockType;
use App\Cms\Fields\Field;

class QuoteBlock extends BlockType
{
    public function slug(): string
    {
        return 'quote';
    }

    public function label(): string
    {
        return 'Quote';
    }

    public function category(): string
    {
        return 'content';
    }

    public function icon(): string
    {
        return 'bi-quote';
    }

    public function fields(): array
    {
        return [
            Field::textarea('text')->label('Quote')->required()->max(1000),
            Field::text('author')->label('Name'),
            Field::text('role')->label('Role or organisation'),
            Field::image('image')->label('Photo'),
        ];
    }
}
