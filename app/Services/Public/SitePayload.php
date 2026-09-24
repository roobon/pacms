<?php

namespace App\Services\Public;

use App\Cms\Design\DesignTokenService;
use App\Services\Settings\SettingsService;
use Laravel\Fortify\Features;

/**
 * Site-wide public data shared by GET /api/v1/site and the SPA shell's initial payload.
 * Contains public settings only (SettingsService decides what is public).
 */
class SitePayload
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly DesignTokenService $tokens,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $site = $this->settings->publicValues()['site'];

        return [
            'name' => $site['name'],
            'tagline' => $site['tagline'],
            'description' => $site['description'],
            'contact' => [
                'email' => $site['contact_email'],
                'phone' => $site['contact_phone'],
                'address' => $site['address'],
            ],
            'timezone' => $site['timezone'],
            'locale' => app()->getLocale(),
            'url' => rtrim((string) config('app.url'), '/'),
            'theme' => [
                'stylesheet' => $this->tokens->stylesheetUrl(),
            ],
            'features' => [
                'registration' => Features::enabled(Features::registration()),
            ],
        ];
    }
}
