<?php

namespace App\Cms\Blocks\Types;

use App\Cms\Fields\Field;
use App\Services\Settings\SettingsService;

/**
 * The copyright line. Only {{year}} and {{site_name}} are replaced (CMS-BLOCK-SCHEMA.md
 * §8.2); no other template syntax exists.
 */
class CopyrightBlock extends SiteBlock
{
    public function slug(): string
    {
        return 'copyright';
    }

    public function label(): string
    {
        return 'Copyright';
    }

    public function icon(): string
    {
        return 'bi-c-circle';
    }

    public function fields(): array
    {
        return [
            Field::text('text')->label('Text')->max(255)->help('Leave empty to use the line from Design → Header & footer. {{year}} and {{site_name}} are filled in.'),
        ];
    }

    public function data(array $content): array
    {
        $settings = app(SettingsService::class);
        $text = trim((string) ($content['text'] ?? '')) ?: (string) $settings->get('navigation', 'copyright');

        return ['text' => self::fill($text, (string) $settings->get('site', 'name'))];
    }

    public static function fill(string $text, string $siteName): string
    {
        return strtr($text, ['{{year}}' => now()->format('Y'), '{{site_name}}' => $siteName]);
    }
}
