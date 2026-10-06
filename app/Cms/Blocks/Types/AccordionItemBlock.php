<?php

namespace App\Cms\Blocks\Types;

use App\Cms\Blocks\BlockType;
use App\Cms\Fields\Field;

class AccordionItemBlock extends BlockType
{
    public function slug(): string
    {
        return 'accordion-item';
    }

    public function label(): string
    {
        return 'Accordion item';
    }

    public function category(): string
    {
        return 'content';
    }

    public function icon(): string
    {
        return 'bi-chevron-bar-expand';
    }

    public function fields(): array
    {
        return [Field::text('title')->label('Title')->required()];
    }

    public function allowedParents(): ?array
    {
        return ['accordion'];
    }

    public function allowedChildren(): ?array
    {
        return ['heading', 'rich-text', 'image', 'button', 'button-group', 'columns', 'video', 'divider'];
    }

    public function defaults(): array
    {
        return [
            'content' => ['title' => 'Question or title'],
            'children' => [['type' => 'rich-text', 'content' => ['html' => '<p>The answer.</p>']]],
        ];
    }
}
