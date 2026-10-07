<?php

namespace App\Cms\Blocks\Types;

use App\Cms\Blocks\BlockType;
use App\Cms\Fields\Field;

/**
 * Custom block structures only (CMS-ARCHITECTURE.md §11.1): renders its children once
 * per row of a repeater field. Inside, blocks can link to the row's fields ("item.…").
 */
class RepeatBlock extends BlockType
{
    public function slug(): string
    {
        return 'repeat';
    }

    public function label(): string
    {
        return 'Repeat for each row';
    }

    public function description(): string
    {
        return 'Shows the blocks inside once for every row of a repeater field.';
    }

    public function category(): string
    {
        return 'structural';
    }

    public function icon(): string
    {
        return 'bi-arrow-repeat';
    }

    public function fields(): array
    {
        return [Field::text('field')->label('Repeater field')->required()->max(80)];
    }

    public function allowedChildren(): ?array
    {
        return ['*'];
    }

    public function contexts(): ?array
    {
        return ['structure'];
    }

    public function isTransparent(): bool
    {
        return true;
    }
}
