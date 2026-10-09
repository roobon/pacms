<?php

namespace App\Cms\Blocks\Types;

use App\Cms\Blocks\BlockType;
use App\Cms\Fields\Field;

/**
 * Full-width slideshow (CMS-ARCHITECTURE.md §6, "slider → slide"): one slide at a time, each
 * with a background image and its own headline, text and buttons. Arrows, dots and optional
 * autoplay; autoplay always has a pause button, stops on hover and keyboard focus and is off
 * for visitors who ask for reduced motion (WCAG 2.2.2).
 */
class SliderBlock extends BlockType
{
    public const HEIGHTS = ['small' => 'Small (50% of the screen)', 'medium' => 'Medium (70%)', 'large' => 'Large (90%)'];

    public function slug(): string
    {
        return 'slider';
    }

    public function label(): string
    {
        return 'Slider';
    }

    public function category(): string
    {
        return 'content';
    }

    public function icon(): string
    {
        return 'bi-collection-play';
    }

    public function description(): string
    {
        return 'Full-width slideshow: one slide at a time, each with an image, headline and buttons.';
    }

    public function fields(): array
    {
        return [
            Field::text('aria_label')->label('Name for screen readers')->default('Highlights')->max(80),
            Field::select('height', self::HEIGHTS)->label('Height')->default('medium'),
            Field::checkbox('autoplay')->label('Move to the next slide automatically')->default(true),
            Field::number('interval')->label('Seconds per slide')->default(7)->min(4)->max(20),
            Field::checkbox('show_arrows')->label('Show arrows')->default(true),
            Field::checkbox('show_dots')->label('Show dots')->default(true),
        ];
    }

    public function allowedParents(): ?array
    {
        return [];
    }

    public function allowedChildren(): ?array
    {
        return ['slide'];
    }

    public function maxChildren(): int
    {
        return 10;
    }

    public function defaults(): array
    {
        $slide = fn (string $heading) => [
            'type' => 'slide',
            'children' => [
                ['type' => 'heading', 'content' => ['text' => $heading, 'level' => '2']],
                ['type' => 'rich-text', 'content' => ['html' => '<p>One sentence that supports the headline.</p>']],
            ],
        ];

        return [
            'content' => ['aria_label' => 'Highlights', 'height' => 'medium', 'autoplay' => true, 'interval' => 7, 'show_arrows' => true, 'show_dots' => true],
            'children' => [$slide('First slide'), $slide('Second slide'), $slide('Third slide')],
        ];
    }
}
