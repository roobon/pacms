<?php

namespace App\Cms\Blocks\Types;

use App\Cms\Blocks\BlockType;

class CtaBlock extends BlockType
{
    public function slug(): string
    {
        return 'cta';
    }

    public function label(): string
    {
        return 'Call to action';
    }

    public function category(): string
    {
        return 'content';
    }

    public function icon(): string
    {
        return 'bi-megaphone';
    }

    public function allowedChildren(): ?array
    {
        return ['heading', 'rich-text', 'button', 'button-group', 'icon'];
    }

    public function defaults(): array
    {
        return [
            'layout' => ['text_align' => 'center', 'padding' => ['top' => ['$token' => 'space.7'], 'bottom' => ['$token' => 'space.7']]],
            'style' => [
                'background' => ['type' => 'color', 'color' => ['$token' => 'color.primary']],
                'typography' => ['color' => ['$token' => 'color.white']],
                'radius' => ['$token' => 'radius.lg'],
            ],
            'children' => [
                ['type' => 'heading', 'content' => ['text' => 'Join us', 'level' => '2']],
                ['type' => 'button-group', 'layout' => ['justify' => 'center']],
            ],
        ];
    }
}
