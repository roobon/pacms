<?php

namespace App\Jobs;

use App\Models\ImportJob;
use App\Models\User;
use App\Services\Exchange\ImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Runs a confirmed JSON import (asset downloads can take a while). The ImportJob row holds
 * the status and report the import screen shows.
 */
class RunImport implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(public readonly int $importJobId, public readonly int $userId) {}

    public function handle(ImportService $imports): void
    {
        $job = ImportJob::query()->find($this->importJobId);
        $user = User::query()->find($this->userId);

        if ($job !== null && $user !== null) {
            $imports->run($job, $user, (array) $job->options);
        }
    }
}
