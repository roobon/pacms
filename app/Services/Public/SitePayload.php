<?php

namespace App\Services\Public;

use App\Cms\Blocks\BlockPayloadResolver;
use App\Cms\Content\ContentTypeRegistry;
use App\Cms\Design\DesignTokenService;
use App\Models\GlobalBlock;
use App\Services\Cache\CacheVersions;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\Cache;
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
        private readonly BlockPayloadResolver $blocks,
        private readonly CacheVersions $versions,
        private readonly ContentTypeRegistry $types,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        // Headers and footers show menus, which link to pages and module items.
        $key = 'pacms:site:'.$this->versions->fingerprint('settings', 'globals', 'menus', 'media', 'pages', ...array_keys($this->types->all()));

        return Cache::remember($key, now()->addDay(), fn () => $this->fresh());
    }

    /**
     * A header or footer global block as a block payload (null: none, or not published).
     *
     * @return list<array<string, mixed>>|null
     */
    public function globalBlock(mixed $id, string $role): ?array
    {
        if (! $id || ! GlobalBlock::query()->whereKey((int) $id)->whereNotNull('published_revision_id')->exists()) {
            return null;
        }

        $blocks = $this->blocks->resolve([[
            'uuid' => strtolower(substr(hash('sha256', "{$role}|{$id}"), 0, 26)),
            'type' => 'global-ref',
            'global_block_id' => (int) $id,
        ]]);

        return $blocks === [] ? null : $blocks;
    }

    /**
     * @return array<string, mixed>
     */
    private function fresh(): array
    {
        $site = $this->settings->publicValues()['site'];
        $navigation = $this->settings->group('navigation');

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
            // Phase 9: the site's header and footer (global blocks), unless a page chooses others.
            'chrome' => [
                'header' => $this->globalBlock($navigation['header_global_block_id'], 'header'),
                'footer' => $this->globalBlock($navigation['footer_global_block_id'], 'footer'),
                'sticky' => (bool) $navigation['sticky_header'],
                'transparent' => (bool) $navigation['transparent_header'],
            ],
        ];
    }
}
