<?php

namespace App\Jobs;

use App\Services\System\HealthCheck;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

/**
 * Proves the queue worker is processing jobs (read by pacms:doctor and the dashboard).
 */
class QueueHeartbeat implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        Cache::forever(HealthCheck::QUEUE_HEARTBEAT, now()->timestamp);
    }
}
