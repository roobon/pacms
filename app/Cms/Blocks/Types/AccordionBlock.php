<?php

namespace App\Cms\Blocks\Types;

use App\Cms\Blocks\BlockType;
use App\Cms\Fields\Field;

class AccordionBlock extends BlockType
{
    public function slug(): string
    {
        return 'accordion';
    }

    public function label(): string
    {
        return 'Accordion';
    }

    public function category(): string
    {
        return 'content';
    }

    public function icon(): string
    {
        return 'bi-list-nested';
    }

    public function description(): string
    {
        return 'Expandable panels whose answers can contain any blocks. For simple question/answer lists use FAQ.';
    }

    public function fields(): array
    {
        return [Field::text('heading')->label('Heading (optional)')];
    }

    public function displayModes(): array
    {
        return ['accordion'];
    }

    public function allowedChildren(): ?array
    {
        return ['accordion-item'];
    }

    public function defaults(): array
    {
        return [
            'display' => ['mode' => 'accordion', 'first_open' => true],
            'children' => [['type' => 'accordion-item'], ['type' => 'accordion-item']],
        ];
    }
}
