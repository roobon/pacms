<?php

namespace App\Jobs;

use App\Models\ExternalSource;
use App\Services\External\ExternalSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Synchronise one external source (queued by pacms:external:sync or "Sync now").
 */
class SyncExternalSource implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 90;

    public function __construct(public readonly int $sourceId, public readonly string $trigger = 'schedule') {}

    public function handle(ExternalSyncService $sync): void
    {
        $source = ExternalSource::query()->find($this->sourceId);
        if ($source !== null && $source->isEnabled()) {
            $sync->sync($source, $this->trigger);
        }
    }
}
