<?php

namespace App\Console\Commands;

use App\Jobs\CheckMediaCoverageSource;
use App\Models\MediaCoverage;
use App\Services\MediaCoverage\SourceChecker;
use Illuminate\Console\Command;

/**
 * Queues the source checks that are due (CMS-ARCHITECTURE.md §19.1). Runs every 15 minutes;
 * every item is checked about once a day at its own random time, so a run is small.
 */
class CheckCoverageSourcesCommand extends Command
{
    protected $signature = 'pacms:coverage:check {--limit=25 : Most checks queued per run}';

    protected $description = 'Queue checks of media-coverage source links that are due';

    public function handle(): int
    {
        $count = 0;
        MediaCoverage::query()
            ->published()
            ->whereNotNull('source_url')
            ->where(fn ($query) => $query->whereNull('next_check_at')->orWhere('next_check_at', '<=', now()))
            ->orderByRaw('next_check_at is not null, next_check_at')
            ->limit(max(1, (int) $this->option('limit')))
            ->get(['id'])
            ->each(function (MediaCoverage $item) use (&$count) {
                // Pushed back first, so a slow queue never queues the same check twice.
                MediaCoverage::query()->whereKey($item->id)->update(['next_check_at' => SourceChecker::nextCheck()]);
                CheckMediaCoverageSource::dispatch($item->id);
                $count++;
            });

        if ($count > 0) {
            $this->components->info("Queued {$count} source check(s).");
        }

        return self::SUCCESS;
    }
}
