<?php

namespace App\Cms\Blocks\Types;

use App\Cms\Fields\Field;
use App\Models\Media;
use App\Services\Settings\SettingsService;

/**
 * The site's logo (Design → Header & footer) linking to the home page, with the site name
 * beside it or instead of it when no logo is set.
 */
class SiteLogoBlock extends SiteBlock
{
    public function slug(): string
    {
        return 'site-logo';
    }

    public function label(): string
    {
        return 'Site logo';
    }

    public function icon(): string
    {
        return 'bi-badge-tm';
    }

    public function fields(): array
    {
        return [
            Field::select('variant', ['default' => 'Normal logo', 'dark' => 'Logo for dark backgrounds'])->label('Logo')->default('default'),
            Field::select('size', ['sm' => 'Small', 'md' => 'Medium', 'lg' => 'Large'])->label('Size')->default('md'),
            Field::checkbox('show_name')->label('Show the site name')->default(true)->help('Always shown when no logo is set.'),
        ];
    }

    public function defaults(): array
    {
        return ['content' => ['variant' => 'default', 'size' => 'md', 'show_name' => true]];
    }

    public function data(array $content): array
    {
        $settings = app(SettingsService::class);
        $id = ($content['variant'] ?? 'default') === 'dark'
            ? ($settings->get('navigation', 'logo_dark_media_id') ?: $settings->get('navigation', 'logo_media_id'))
            : $settings->get('navigation', 'logo_media_id');
        $logo = $id ? Media::query()->find((int) $id) : null;

        return [
            'name' => (string) $settings->get('site', 'name'),
            'logo' => $logo !== null && $logo->isPublic() ? $logo->toImageArray('240px') : null,
        ];
    }
}
