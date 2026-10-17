<?php

namespace App\Cms\Blocks\Types;

use App\Cms\Blocks\BlockType;
use App\Cms\Fields\Field;

/**
 * Split (Phase 11W): a photo filling one half of the screen to its edge, and the blocks
 * inside it on the other half, lined up with the site width. Stacks on phones (photo first).
 * Without a photo yet, the photo half shows a quiet panel, so a new page still looks planned.
 */
class SplitBlock extends BlockType
{
    public const SIDES = ['left' => 'Photo on the left', 'right' => 'Photo on the right'];

    public const WIDTHS = ['half' => 'Half', 'wide' => 'Wider photo (60%)', 'narrow' => 'Narrower photo (40%)'];

    public const HEIGHTS = ['auto' => 'As tall as the text', 'md' => 'Half the screen', 'lg' => 'Most of the screen'];

    public function slug(): string
    {
        return 'split';
    }

    public function label(): string
    {
        return 'Split: photo and text';
    }

    public function description(): string
    {
        return 'A photo filling half the screen to its edge, with text and buttons beside it.';
    }

    public function category(): string
    {
        return 'content';
    }

    public function icon(): string
    {
        return 'bi-layout-split';
    }

    public function allowedParents(): ?array
    {
        return [];
    }

    public function allowedChildren(): ?array
    {
        return ['heading', 'rich-text', 'button', 'button-group', 'list', 'icon', 'spacer', 'divider', 'statistics', 'quote', 'image', 'logos'];
    }

    public function fields(): array
    {
        return [
            Field::image('image')->label('Photo')->help('Fills its half of the screen and is cropped to fit.'),
            Field::text('alt')->label('Alternative text')->help('Leave empty to use the alt text from the media library.'),
            Field::select('image_side', self::SIDES)->label('Photo side')->default('left'),
            Field::select('image_width', self::WIDTHS)->label('Photo width')->default('half'),
            Field::select('height', self::HEIGHTS)->label('Height')->default('md'),
        ];
    }

    public function defaults(): array
    {
        return [
            'content' => ['image_side' => 'left', 'image_width' => 'half', 'height' => 'md'],
            'children' => [
                ['type' => 'heading', 'content' => ['text' => 'A heading about this part of your work', 'level' => '2']],
                ['type' => 'rich-text', 'content' => ['html' => '<p>Two or three sentences that explain it. Replace the photo with one of your own.</p>']],
                ['type' => 'button-group', 'children' => [
                    ['type' => 'button', 'content' => ['label' => 'Read more', 'link' => ['type' => 'url', 'url' => '/about'], 'variant' => 'primary']],
                ]],
            ],
        ];
    }
}
