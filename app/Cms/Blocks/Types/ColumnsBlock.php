<?php

namespace App\Cms\Blocks\Types;

use App\Cms\Blocks\BlockType;

class ColumnsBlock extends BlockType
{
    public function slug(): string
    {
        return 'columns';
    }

    public function label(): string
    {
        return 'Columns';
    }

    public function category(): string
    {
        return 'layout';
    }

    public function icon(): string
    {
        return 'bi-layout-three-columns';
    }

    public function description(): string
    {
        return 'Side-by-side columns. Widths are set per device in Layout; on phones they stack by default.';
    }

    public function allowedChildren(): ?array
    {
        return ['column'];
    }

    public function maxChildren(): int
    {
        return 6;
    }

    public function defaults(): array
    {
        return [
            'layout' => ['columns' => ['desktop' => [6, 6], 'mobile' => [12, 12]], 'gap' => ['$token' => 'space.5']],
            'children' => [['type' => 'column'], ['type' => 'column']],
        ];
    }
}
