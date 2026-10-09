<?php

namespace App\Cms\Blocks\Types;

use App\Cms\Blocks\BlockType;
use App\Cms\Fields\Field;

/**
 * One slide of a Slider: a background image with a shade for readable text, and the
 * slide's headline, text and buttons.
 */
class SlideBlock extends BlockType
{
    public const SHADES = ['dark' => 'Dark (white text)', 'light' => 'Light (dark text)', 'none' => 'None'];

    public function slug(): string
    {
        return 'slide';
    }

    public function label(): string
    {
        return 'Slide';
    }

    public function category(): string
    {
        return 'content';
    }

    public function icon(): string
    {
        return 'bi-card-image';
    }

    public function fields(): array
    {
        return [
            Field::image('image')->label('Background image'),
            Field::select('shade', self::SHADES)->label('Shade over the image')->default('dark'),
            Field::select('align', ['start' => 'Left', 'center' => 'Centre'])->label('Text position')->default('start'),
        ];
    }

    public function allowedParents(): ?array
    {
        return ['slider'];
    }

    public function allowedChildren(): ?array
    {
        return ['heading', 'rich-text', 'button', 'button-group', 'icon', 'spacer'];
    }

    public function defaults(): array
    {
        return [
            'content' => ['shade' => 'dark', 'align' => 'start'],
            'children' => [
                ['type' => 'heading', 'content' => ['text' => 'Slide headline', 'level' => '2']],
                ['type' => 'rich-text', 'content' => ['html' => '<p>One sentence that supports the headline.</p>']],
            ],
        ];
    }
}
