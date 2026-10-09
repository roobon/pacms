<?php

namespace App\Cms\Blocks\Types;

use App\Cms\Blocks\BlockType;
use App\Cms\Fields\Field;

/**
 * Published testimonials (CMS-ARCHITECTURE.md §18): quote cards in a grid, list, carousel,
 * quote slider, masonry, one featured quote with the rest below, or a single large quote.
 */
class TestimonialsBlock extends BlockType
{
    public function slug(): string
    {
        return 'testimonials';
    }

    public function label(): string
    {
        return 'Testimonials';
    }

    public function category(): string
    {
        return 'dynamic';
    }

    public function icon(): string
    {
        return 'bi-chat-quote';
    }

    public function fields(): array
    {
        return [
            Field::text('heading')->label('Heading (optional)'),
            Field::text('empty_text')->label('Text when there is nothing to show')->default('No testimonials yet.'),
            Field::checkbox('show_photo')->label('Show photos')->default(true),
            Field::checkbox('show_rating')->label('Show ratings')->default(true),
        ];
    }

    public function sourceModes(): array
    {
        return ['dynamic'];
    }

    public function dynamicEntity(): ?string
    {
        return 'testimonials';
    }

    public function displayModes(): array
    {
        return ['grid', 'carousel', 'quote-slider', 'single', 'featured', 'list', 'masonry'];
    }

    public function defaults(): array
    {
        return [
            'content' => ['heading' => 'What people say', 'empty_text' => 'No testimonials yet.', 'show_photo' => true, 'show_rating' => true],
            'source' => ['mode' => 'dynamic', 'provider' => 'cms', 'entity' => 'testimonials', 'order' => 'position', 'limit' => 6],
            'display' => ['mode' => 'grid', 'columns' => ['desktop' => 3, 'tablet' => 2, 'mobile' => 1], 'card_style' => 'elevated'],
        ];
    }
}
