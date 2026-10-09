<?php

namespace App\Cms\Blocks\Types;

use App\Cms\Fields\Field;
use App\Services\Settings\SettingsService;

/**
 * Links to the organisation's social profiles (Design → Header & footer).
 */
class SocialLinksBlock extends SiteBlock
{
    /** network => [label, icon] */
    public const NETWORKS = [
        'facebook' => ['Facebook', 'bi-facebook'],
        'youtube' => ['YouTube', 'bi-youtube'],
        'linkedin' => ['LinkedIn', 'bi-linkedin'],
        'instagram' => ['Instagram', 'bi-instagram'],
        'x' => ['X', 'bi-twitter-x'],
    ];

    public function slug(): string
    {
        return 'social-links';
    }

    public function label(): string
    {
        return 'Social links';
    }

    public function icon(): string
    {
        return 'bi-share';
    }

    public function fields(): array
    {
        return [
            Field::select('style', ['icons' => 'Icons', 'labels' => 'Icons and names'])->label('Style')->default('icons'),
            Field::text('heading')->label('Heading (optional)')->max(120),
        ];
    }

    public function defaults(): array
    {
        return ['content' => ['style' => 'icons']];
    }

    public function data(array $content): array
    {
        $links = [];
        foreach ((array) app(SettingsService::class)->get('navigation', 'social', []) as $network => $url) {
            if (isset(self::NETWORKS[$network]) && is_string($url) && $url !== '') {
                $links[] = ['network' => $network, 'label' => self::NETWORKS[$network][0], 'icon' => self::NETWORKS[$network][1], 'url' => $url];
            }
        }

        return ['links' => $links];
    }
}
