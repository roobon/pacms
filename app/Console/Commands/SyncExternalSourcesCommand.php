<?php

namespace App\Console\Commands;

use App\Jobs\SyncExternalSource;
use App\Models\ExternalSource;
use App\Services\External\ExternalSyncService;
use Illuminate\Console\Command;

/**
 * Queue the external sources that are due (run every minute by the scheduler), or sync one
 * source at once with --source=<slug>.
 */
class SyncExternalSourcesCommand extends Command
{
    protected $signature = 'pacms:external:sync
        {--source= : Sync this source (its slug) now, in this process}
        {--limit=20 : Most sources queued per run}';

    protected $description = 'Synchronise external sources (feeds) that are due';

    public function handle(ExternalSyncService $sync): int
    {
        if ($slug = $this->option('source')) {
            $source = ExternalSource::query()->where('slug', (string) $slug)->first();
            if ($source === null) {
                $this->components->error("No external source \"{$slug}\".");

                return self::FAILURE;
            }
            $log = $sync->sync($source, 'manual');
            $log->status === 'ok'
                ? $this->components->info("{$source->name}: {$log->items_fetched} items ({$log->items_created} new).")
                : $this->components->error("{$source->name}: {$log->error}");

            return $log->status === 'ok' ? self::SUCCESS : self::FAILURE;
        }

        $count = 0;
        ExternalSource::query()->due()->orderByRaw('next_sync_at is not null, next_sync_at')->limit(max(1, (int) $this->option('limit')))->get(['id', 'sync_interval_minutes'])
            ->each(function (ExternalSource $source) use (&$count) {
                // Pushed back first, so a slow queue never queues the same source twice.
                ExternalSource::query()->whereKey($source->id)->update(['next_sync_at' => now()->addMinutes($source->sync_interval_minutes)]);
                SyncExternalSource::dispatch($source->id);
                $count++;
            });
        if ($count > 0) {
            $this->components->info("Queued {$count} source(s).");
        }

        return self::SUCCESS;
    }
}
