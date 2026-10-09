<?php

use App\Jobs\QueueHeartbeat;
use App\Models\ActivityLog;
use App\Services\System\HealthCheck;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduler — driven by one cron entry: * * * * * php artisan schedule:run
|--------------------------------------------------------------------------
*/

Schedule::call(fn () => Cache::forever(HealthCheck::SCHEDULER_HEARTBEAT, now()->timestamp))
    ->name('pacms:scheduler-heartbeat')
    ->everyMinute();

Schedule::job(new QueueHeartbeat)->everyFiveMinutes();

// Shared hosting: no Supervisor, so start a short-lived worker every minute
// (DEPLOYMENT-ARCHITECTURE.md §4). Disabled when a managed worker runs.
if (! config('pacms.queue.managed_worker')) {
    Schedule::command('queue:work --stop-when-empty --max-time=55 --tries=3')
        ->everyMinute()
        ->withoutOverlapping()
        ->runInBackground();
}

Schedule::command('model:prune', ['--model' => [ActivityLog::class]])->daily();

// Scheduled publishing and revision retention.
Schedule::command('pacms:publish-scheduled')->everyMinute()->withoutOverlapping();
Schedule::command('pacms:revisions:prune')->weekly();

// Media coverage: check original links that are due (each about once a day, spread out).
Schedule::command('pacms:coverage:check')->everyFifteenMinutes()->withoutOverlapping();

// Testimonials: remove submitters' IP addresses after the retention period.
Schedule::command('pacms:testimonials:purge-ips')->daily();
