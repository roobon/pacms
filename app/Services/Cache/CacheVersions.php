<?php

namespace App\Services\Cache;

use Illuminate\Support\Facades\Cache;

/**
 * Cache-version counters per dependency group (CMS-ARCHITECTURE.md §6.5).
 *
 * Cached payloads include the versions of the groups they depend on in their key;
 * bumping a group makes every dependent entry unreachable. Works on the file and
 * database cache drivers (no tag support needed).
 */
class CacheVersions
{
    public function get(string $group): int
    {
        return (int) Cache::rememberForever($this->key($group), fn () => 1);
    }

    public function bump(string ...$groups): void
    {
        foreach ($groups as $group) {
            Cache::forever($this->key($group), $this->get($group) + 1);
        }
    }

    /**
     * Compact key fragment for several groups: a hash of their versions ("pages3.media7…").
     * Hashed so keys stay short however many content types exist (the database cache
     * store's key column holds 255 characters).
     */
    public function fingerprint(string ...$groups): string
    {
        return sha1(implode('.', array_map(fn ($group) => $group.$this->get($group), $groups)));
    }

    private function key(string $group): string
    {
        return "pacms:cache-version:{$group}";
    }
}
