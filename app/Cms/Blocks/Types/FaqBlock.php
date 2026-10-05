<?php

namespace App\Cms\Blocks\Types;

use App\Cms\Blocks\BlockType;
use App\Cms\Fields\Field;

class FaqBlock extends BlockType
{
    public function slug(): string
    {
        return 'faq';
    }

    public function label(): string
    {
        return 'FAQ';
    }

    public function category(): string
    {
        return 'content';
    }

    public function icon(): string
    {
        return 'bi-question-circle';
    }

    public function fields(): array
    {
        return [
            Field::text('heading')->label('Heading (optional)'),
            Field::repeater('items', [
                Field::text('question')->label('Question')->required(),
                Field::richText('answer')->label('Answer')->required()->max(20000),
            ])->label('Questions')->items(1, 40),
        ];
    }

    public function displayModes(): array
    {
        return ['accordion'];
    }

    public function defaults(): array
    {
        return [
            'content' => ['items' => [['question' => 'A frequently asked question?', 'answer' => '<p>The answer.</p>']]],
            'display' => ['mode' => 'accordion'],
        ];
    }
}
