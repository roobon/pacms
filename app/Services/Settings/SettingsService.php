<?php

namespace App\Services\Settings;

use App\Models\Setting;
use App\Models\User;
use App\Services\Cache\CacheVersions;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Database-backed settings, cached per group.
 *
 * Only keys declared in self::DEFINITIONS can be stored, so arbitrary input can never
 * create settings, and the public/private flag comes from code, not from requests.
 * Secrets never belong here (use .env or encrypted provider accounts).
 */
class SettingsService
{
    /**
     * group => key => [default, public?]
     * Defaults are neutral placeholders; real organisational content is entered in the admin.
     */
    public const DEFINITIONS = [
        'site' => [
            'name' => ['Your Organization', true],
            'tagline' => ['', true],
            'description' => ['', true],
            'contact_email' => ['', true],
            'contact_phone' => ['', true],
            'address' => ['', true],
            'timezone' => ['Asia/Dhaka', true],
            'homepage_page_id' => [null, true],
        ],
        'seo' => [
            // Extra robots.txt lines (the Sitemap line is always added).
            'robots_txt' => ["User-agent: *\nDisallow: /admin\nDisallow: /account\nDisallow: /preview", false],
        ],
        'design' => [
            'tokens' => [[], false],
            'stylesheet' => [null, true],
        ],
        'content' => [
            // Module sidebars (Phase 8): type key => ['global_block_id' => int, 'position' => left|right].
            'sidebars' => [[], false],
        ],
        // Phase 9: the site's header, footer, logo and social profiles.
        'navigation' => [
            'header_global_block_id' => [null, true],
            'footer_global_block_id' => [null, true],
            'sticky_header' => [true, true],
            'transparent_header' => [false, true],
            'logo_media_id' => [null, true],
            'logo_dark_media_id' => [null, true],
            // network => URL (facebook, youtube, linkedin, instagram, x).
            'social' => [[], true],
            // Only {{year}} and {{site_name}} are replaced.
            'copyright' => ['© {{year}} {{site_name}}', true],
        ],
        'media' => [
            // D-10: SVG uploads are off by default. When on, only users with
            // media.upload_svg may upload them, and every file is sanitised.
            'allow_svg' => [false, false],
        ],
    ];

    public function get(string $group, string $key, mixed $default = null): mixed
    {
        return Arr::get($this->group($group), $key, $default);
    }

    /**
     * @return array<string, mixed>
     */
    public function group(string $group): array
    {
        $this->assertGroup($group);

        $stored = Cache::rememberForever($this->cacheKey($group), fn () => Setting::query()
            ->where('group', $group)
            ->pluck('value', 'key')
            ->all());

        $defaults = array_map(fn (array $definition) => $definition[0], self::DEFINITIONS[$group]);

        return array_replace($defaults, array_intersect_key($stored, $defaults));
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function set(string $group, array $values, ?User $user = null): void
    {
        $this->assertGroup($group);

        $unknown = array_diff(array_keys($values), array_keys(self::DEFINITIONS[$group]));
        if ($unknown !== []) {
            throw new InvalidArgumentException('Unknown setting(s): '.implode(', ', $unknown));
        }

        DB::transaction(function () use ($group, $values, $user) {
            foreach ($values as $key => $value) {
                Setting::query()->updateOrCreate(
                    ['group' => $group, 'key' => $key],
                    ['value' => $value, 'is_public' => self::DEFINITIONS[$group][$key][1], 'updated_by' => $user?->id],
                );
            }
        });

        Cache::forget($this->cacheKey($group));

        // Public payloads embed settings (site name in titles, homepage …).
        app(CacheVersions::class)->bump('settings');
    }

    /**
     * Settings that may be exposed through the public API, grouped.
     *
     * @return array<string, array<string, mixed>>
     */
    public function publicValues(): array
    {
        $public = [];
        foreach (self::DEFINITIONS as $group => $definitions) {
            $values = $this->group($group);
            foreach ($definitions as $key => [, $isPublic]) {
                if ($isPublic) {
                    $public[$group][$key] = $values[$key];
                }
            }
        }

        return $public;
    }

    private function assertGroup(string $group): void
    {
        if (! isset(self::DEFINITIONS[$group])) {
            throw new InvalidArgumentException("Unknown settings group [{$group}].");
        }
    }

    private function cacheKey(string $group): string
    {
        return "pacms:settings:{$group}";
    }
}
