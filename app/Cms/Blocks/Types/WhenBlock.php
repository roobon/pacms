<?php

namespace App\Cms\Blocks\Types;

use App\Cms\Blocks\BlockType;
use App\Cms\Fields\Field;

/**
 * Custom block structures only (CMS-ARCHITECTURE.md §11.1): shows its children only when
 * a field is filled, empty or equal to a fixed value. No expressions.
 */
class WhenBlock extends BlockType
{
    public const OPERATORS = ['filled' => 'is filled in', 'empty' => 'is empty', 'equals' => 'equals'];

    public function slug(): string
    {
        return 'when';
    }

    public function label(): string
    {
        return 'Show when';
    }

    public function description(): string
    {
        return 'Shows the blocks inside only when a field is filled in, empty or has a given value.';
    }

    public function category(): string
    {
        return 'structural';
    }

    public function icon(): string
    {
        return 'bi-signpost-split';
    }

    public function fields(): array
    {
        return [
            Field::text('field')->label('Field')->required()->max(80),
            Field::select('operator', self::OPERATORS)->label('Condition')->default('filled')->required(),
            Field::text('value')->label('Value (for “equals”)')->max(255),
        ];
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
