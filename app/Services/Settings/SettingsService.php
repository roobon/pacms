<?php

namespace App\Services\Settings;

use App\Models\Setting;
use App\Models\User;
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
        ],
        'design' => [
            'tokens' => [[], false],
            'stylesheet' => [null, true],
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
