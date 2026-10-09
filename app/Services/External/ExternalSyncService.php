<?php

namespace App\Services\External;

use App\Cms\External\ExternalContentProvider;
use App\Cms\External\ProviderRegistry;
use App\Models\ExternalItem;
use App\Models\ExternalSource;
use App\Models\ExternalSyncLog;
use App\Services\Cache\CacheVersions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Synchronising external sources (CMS-ARCHITECTURE.md §15.3).
 *
 * - One sync per source at a time (lock).
 * - Success: items are added or updated, the oldest beyond "max items" are removed, the
 *   next sync is planned, and pages showing the source are refreshed.
 * - Failure: the items already stored keep showing (stale-while-error); the error is kept
 *   for admins and the next try waits longer each time (×2, at most a day).
 * - Every attempt is logged (kept 90 days).
 */
class ExternalSyncService
{
    public const MAX_BACKOFF_MINUTES = 1440;

    public function __construct(
        private readonly ProviderRegistry $providers,
        private readonly CacheVersions $versions,
    ) {}

    /**
     * @param  string  $trigger  schedule|manual
     */
    public function sync(ExternalSource $source, string $trigger = 'schedule'): ExternalSyncLog
    {
        $lock = Cache::lock("pacms:external-sync:{$source->id}", 120);
        if (! $lock->get()) {
            return $this->log($source, $trigger, 'skipped', error: __('A sync of this source is already running.'));
        }

        $started = microtime(true);
        try {
            $result = $this->provider($source)->fetch($source);
            [$created, $updated] = $this->store($source, $result['items']);

            $source->forceFill([
                'format' => $result['format'] ?? $source->format,
                'source_website' => $source->source_website ?: $result['website'],
                'last_synced_at' => now(),
                'last_success_at' => now(),
                'last_status' => 'ok',
                'last_error' => null,
                'consecutive_failures' => 0,
                'next_sync_at' => now()->addMinutes($source->sync_interval_minutes),
            ])->save();
            $this->versions->bump('external');

            return $this->log($source, $trigger, 'ok', count($result['items']), $created, $updated, $started);
        } catch (Throwable $e) {
            $failures = $source->consecutive_failures + 1;
            $message = $this->message($e);
            $source->forceFill([
                'last_synced_at' => now(),
                'last_status' => 'error',
                'last_error' => Str::limit($message, 1000),
                'consecutive_failures' => $failures,
                'next_sync_at' => now()->addMinutes(min(self::MAX_BACKOFF_MINUTES, $source->sync_interval_minutes * 2 ** min($failures, 10))),
            ])->save();
            if (! $e instanceof RuntimeException) {
                report($e);
            }

            return $this->log($source, $trigger, 'error', started: $started, error: $message);
        } finally {
            $lock->release();
        }
    }

    /**
     * Read the source without storing anything (the "Test" button).
     *
     * @return array{ok: bool, message: string, format?: string|null, title?: string|null, items?: list<array<string, mixed>>}
     */
    public function test(ExternalSource $source): array
    {
        try {
            $result = $this->provider($source)->fetch($source);
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $this->message($e)];
        }

        return [
            'ok' => true,
            'message' => trans_choice('Found :count item.|Found :count items.', count($result['items']), ['count' => count($result['items'])]),
            'format' => $result['format'],
            'title' => $result['title'],
            'items' => array_slice($result['items'], 0, 5),
        ];
    }

    /**
     * Delete the stored items (they come back with the next sync).
     */
    public function clear(ExternalSource $source): void
    {
        ExternalItem::query()->where('external_source_id', $source->id)->delete();
        $source->forceFill(['next_sync_at' => now()])->save();
        $this->versions->bump('external');
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array{0: int, 1: int} created, updated
     */
    private function store(ExternalSource $source, array $items): array
    {
        $created = $updated = 0;
        DB::transaction(function () use ($source, $items, &$created, &$updated) {
            foreach (array_slice($items, 0, $source->max_items) as $data) {
                $item = ExternalItem::query()->firstOrNew(['external_source_id' => $source->id, 'external_id' => $data['external_id']]);
                $item->exists ? $updated++ : $created++;
                $item->fill(array_intersect_key($data, array_flip(['title', 'link', 'excerpt', 'description', 'author', 'category', 'image_url', 'published_at', 'source_updated_at'])))->save();
            }

            // Keep the newest "max items" (items without a date count as oldest).
            $keep = ExternalItem::query()->where('external_source_id', $source->id)
                ->orderByRaw('published_at is null')->orderByDesc('published_at')->orderByDesc('id')
                ->limit($source->max_items)->pluck('id');
            ExternalItem::query()->where('external_source_id', $source->id)->whereNotIn('id', $keep)->delete();
        });

        return [$created, $updated];
    }

    private function provider(ExternalSource $source): ExternalContentProvider
    {
        return $this->providers->find($source->provider) ?? throw new RuntimeException(__('This kind of source is not available.'));
    }

    /**
     * A message for admins; unexpected errors are not shown in detail.
     */
    private function message(Throwable $e): string
    {
        return $e instanceof RuntimeException ? $e->getMessage() : __('Something went wrong while reading this source. The details are in the application log.');
    }

    private function log(ExternalSource $source, string $trigger, string $status, int $fetched = 0, int $created = 0, int $updated = 0, ?float $started = null, ?string $error = null): ExternalSyncLog
    {
        return ExternalSyncLog::query()->create([
            'external_source_id' => $source->id,
            'trigger' => $trigger,
            'status' => $status,
            'items_fetched' => $fetched,
            'items_created' => $created,
            'items_updated' => $updated,
            'duration_ms' => $started === null ? 0 : (int) round((microtime(true) - $started) * 1000),
            'error' => $error === null ? null : Str::limit($error, 2000),
            'created_at' => now(),
        ]);
    }
}
