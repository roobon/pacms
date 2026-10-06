<?php

namespace App\Cms\Blocks\Types;

use App\Cms\Blocks\BlockType;

class ButtonGroupBlock extends BlockType
{
    public function slug(): string
    {
        return 'button-group';
    }

    public function label(): string
    {
        return 'Buttons';
    }

    public function category(): string
    {
        return 'basic';
    }

    public function icon(): string
    {
        return 'bi-ui-checks-grid';
    }

    public function allowedChildren(): ?array
    {
        return ['button'];
    }

    public function maxChildren(): int
    {
        return 6;
    }

    public function defaults(): array
    {
        return ['children' => [['type' => 'button']]];
    }
}
