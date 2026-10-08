<?php

namespace App\Cms\Blocks\Types;

use App\Cms\Fields\Field;

/**
 * Partners: a logo wall (default) or cards, in display order; logos link to the partners'
 * websites. A category or featured partners only through the source filters.
 */
class PartnersBlock extends ContentCollectionBlock
{
    protected function entity(): string
    {
        return 'partners';
    }

    protected function emptyText(): string
    {
        return 'No partners yet.';
    }

    protected function sourceDefaults(): array
    {
        return ['order' => 'position', 'limit' => 24];
    }

    public function label(): string
    {
        return 'Partners';
    }

    public function icon(): string
    {
        return 'bi-building';
    }

    public function fields(): array
    {
        return [
            Field::text('heading')->label('Heading (optional)'),
            Field::select('style', ['logos' => 'Logo wall', 'cards' => 'Cards with description'])->label('Show as')->default('logos'),
            Field::select('logo_size', ['sm' => 'Small', 'md' => 'Medium', 'lg' => 'Large'])->label('Logo size (logo wall)')->default('md'),
            Field::checkbox('grayscale')->label('Grey logos, in colour on hover (logo wall)')->default(false),
            Field::text('empty_text')->label('Text when there is nothing to show')->default($this->emptyText()),
        ];
    }

    public function defaults(): array
    {
        $defaults = parent::defaults();
        $defaults['content'] = ['heading' => 'Our partners', 'style' => 'logos', 'logo_size' => 'md', 'grayscale' => false, 'empty_text' => $this->emptyText()];

        return $defaults;
    }
}
