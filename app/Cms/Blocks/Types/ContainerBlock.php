<?php

namespace App\Cms\Blocks\Types;

use App\Cms\Blocks\BlockType;

class ContainerBlock extends BlockType
{
    public function slug(): string
    {
        return 'container';
    }

    public function label(): string
    {
        return 'Container';
    }

    public function category(): string
    {
        return 'layout';
    }

    public function icon(): string
    {
        return 'bi-bounding-box';
    }

    public function description(): string
    {
        return 'Groups blocks so they can share spacing, width and background.';
    }

    public function allowedChildren(): ?array
    {
        return ['*'];
    }

    /** @return list<string> */
    public function excludedChildren(): array
    {
        return ['section', 'hero'];
    }
}
