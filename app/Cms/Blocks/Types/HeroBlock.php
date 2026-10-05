<?php

namespace App\Cms\Blocks\Types;

use App\Cms\Blocks\BlockType;

class HeroBlock extends BlockType
{
    public function slug(): string
    {
        return 'hero';
    }

    public function label(): string
    {
        return 'Hero';
    }

    public function category(): string
    {
        return 'content';
    }

    public function icon(): string
    {
        return 'bi-image-alt';
    }

    public function description(): string
    {
        return 'Large opening area with a background image or colour and headline content.';
    }

    public function allowedParents(): ?array
    {
        return [];
    }

    public function allowedChildren(): ?array
    {
        return ['heading', 'rich-text', 'button', 'button-group', 'image', 'icon', 'container', 'spacer'];
    }

    public function defaults(): array
    {
        return [
            'layout' => [
                'container' => 'boxed',
                'min_height' => ['value' => 60, 'unit' => 'vh'],
                'text_align' => 'center',
                'padding' => ['top' => ['$token' => 'space.9'], 'bottom' => ['$token' => 'space.9']],
            ],
            'style' => [
                'background' => ['type' => 'color', 'color' => ['$token' => 'color.bg-dark']],
                'typography' => ['color' => ['$token' => 'color.white']],
            ],
            'children' => [
                ['type' => 'heading', 'content' => ['text' => 'A clear, inspiring headline', 'level' => '1']],
                ['type' => 'rich-text', 'content' => ['html' => '<p>One or two sentences that explain what you do.</p>']],
                ['type' => 'button-group', 'layout' => ['justify' => 'center']],
            ],
        ];
    }
}
