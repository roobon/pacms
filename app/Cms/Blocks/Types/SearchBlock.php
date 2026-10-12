<?php

namespace App\Cms\Blocks\Types;

use App\Cms\Fields\Field;

/**
 * Site search for headers and pages (Phase 11): a search icon that opens a box, or an
 * always-visible box. Both lead to /search.
 */
class SearchBlock extends SiteBlock
{
    public function slug(): string
    {
        return 'search';
    }

    public function label(): string
    {
        return 'Search';
    }

    public function icon(): string
    {
        return 'bi-search';
    }

    public function fields(): array
    {
        return [
            Field::select('style', ['icon' => 'Icon that opens a box (headers)', 'box' => 'Search box (pages, footers)'])->label('Style')->default('icon'),
            Field::text('placeholder')->label('Placeholder text')->default('Search…')->max(80),
        ];
    }

    public function defaults(): array
    {
        return ['content' => ['style' => 'icon', 'placeholder' => 'Search…']];
    }
}
