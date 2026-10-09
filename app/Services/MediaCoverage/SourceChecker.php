<?php

namespace App\Services\MediaCoverage;

use App\Models\MediaCoverage;
use App\Services\Cache\CacheVersions;
use App\Support\Http\DownloadFailedException;
use App\Support\Http\SafeHttpClient;
use App\Support\Http\UnresolvableHostException;
use App\Support\Http\UnsafeUrlException;
use Illuminate\Support\Carbon;

/**
 * Checks whether a coverage item's original link still works (CMS-ARCHITECTURE.md §19.1).
 *
 *   final 2xx                       → available (failures reset)
 *   404/410, unknown host           → a failure; 3 in a row → unavailable
 *   401/403/429/5xx, timeout, other → unverified: many news sites block bots, so this
 *                                     never counts against the link
 *
 * Runs from the scheduler (spread across the day) or the admin "Check now" button, never
 * while a visitor loads a page.
 */
class SourceChecker
{
    public const FAILURES_BEFORE_UNAVAILABLE = 3;

    public function __construct(
        private readonly SafeHttpClient $http,
        private readonly CacheVersions $cache,
    ) {}

    public function check(MediaCoverage $item): MediaCoverage
    {
        if ($item->source_url === null || $item->source_url === '') {
            $item->forceFill(['availability' => 'unknown', 'http_status' => null, 'last_check_error' => null, 'consecutive_failures' => 0, 'next_check_at' => null])->saveQuietly();

            return $item;
        }

        $status = null;
        $error = null;
        try {
            $status = $this->http->probe($item->source_url);
            $result = match (true) {
                $status >= 200 && $status < 300 => 'available',
                in_array($status, [404, 410], true) => 'failure',
                default => 'unverified',
            };
            if ($result !== 'available') {
                $error = __('The server answered with HTTP :status.', ['status' => $status]);
            }
        } catch (UnresolvableHostException $e) {
            [$result, $error] = ['failure', $e->getMessage()];
        } catch (UnsafeUrlException $e) {
            // Points to a private network or an unusual port: visitors cannot use it either.
            [$result, $error] = ['failure', $e->getMessage()];
        } catch (DownloadFailedException $e) {
            [$result, $error] = ['unverified', $e->getMessage()];
        }

        $failures = match ($result) {
            'available' => 0,
            'failure' => min(255, $item->consecutive_failures + 1),
            default => $item->consecutive_failures,
        };

        $availability = match ($result) {
            'available' => 'available',
            'unverified' => 'unverified',
            // A single failure may be a hiccup; the link stays as it was until it fails repeatedly.
            default => $failures >= self::FAILURES_BEFORE_UNAVAILABLE ? 'unavailable' : $item->availability,
        };

        $changed = $availability !== $item->availability;
        $item->forceFill([
            'availability' => $availability,
            'http_status' => $status,
            'last_check_error' => $error === null ? null : mb_substr($error, 0, 512),
            'consecutive_failures' => $failures,
            'last_checked_at' => now(),
            'next_check_at' => self::nextCheck(),
        ])->saveQuietly();

        if ($changed) {
            // Coverage pages and blocks show a different link or the archive now.
            $this->cache->bump('media_coverage');
        }

        return $item;
    }

    /**
     * About a day from now, at a random time, so checks spread across the day.
     */
    public static function nextCheck(): Carbon
    {
        return now()->addHours(20)->addMinutes(random_int(0, 8 * 60));
    }
}
