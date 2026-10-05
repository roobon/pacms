<?php

namespace App\Cms\Blocks\Types;

use App\Cms\Blocks\BlockType;

class ColumnBlock extends BlockType
{
    public function slug(): string
    {
        return 'column';
    }

    public function label(): string
    {
        return 'Column';
    }

    public function category(): string
    {
        return 'layout';
    }

    public function icon(): string
    {
        return 'bi-layout-sidebar';
    }

    public function allowedParents(): ?array
    {
        return ['columns'];
    }

    public function allowedChildren(): ?array
    {
        return ['*'];
    }

    /** @return list<string> */
    public function excludedChildren(): array
    {
        return ['section', 'hero', 'columns', 'column'];
    }
}
