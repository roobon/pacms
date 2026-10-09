<?php

namespace App\Cms\Blocks\Types;

use App\Cms\Fields\Field;
use App\Services\Navigation\MenuService;

/**
 * A menu from Design → Menus. Horizontal menus open sub-menus with buttons (disclosure
 * pattern) and become a slide-in drawer on small screens; vertical menus list every level.
 */
class MenuBlock extends SiteBlock
{
    public function slug(): string
    {
        return 'menu';
    }

    public function label(): string
    {
        return 'Menu';
    }

    public function icon(): string
    {
        return 'bi-list';
    }

    public function fields(): array
    {
        return [
            Field::select('menu', app(MenuService::class)->choices())->label('Menu')->help('Menus are made under Design → Menus.'),
            Field::select('style', ['horizontal' => 'Horizontal (header)', 'vertical' => 'Vertical list (footer, sidebar)'])->label('Style')->default('horizontal'),
            Field::text('aria_label')->label('Name for screen readers')->default('Main')->max(60)->help('e.g. Main, Footer. Read out as "Main navigation".'),
            Field::text('heading')->label('Heading (vertical menus)')->max(120),
        ];
    }

    public function defaults(): array
    {
        return ['content' => ['menu' => 'main', 'style' => 'horizontal', 'aria_label' => 'Main']];
    }

    public function data(array $content): array
    {
        $slug = (string) ($content['menu'] ?? '');

        return ['items' => $slug === '' ? [] : app(MenuService::class)->resolve($slug)];
    }
}
