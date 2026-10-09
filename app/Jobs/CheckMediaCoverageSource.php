<?php

namespace App\Jobs;

use App\Models\MediaCoverage;
use App\Services\MediaCoverage\SourceChecker;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Checks one coverage item's original link (CMS-ARCHITECTURE.md §19.1).
 */
class CheckMediaCoverageSource implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(public int $coverageId) {}

    public function uniqueId(): string
    {
        return (string) $this->coverageId;
    }

    public function handle(SourceChecker $checker): void
    {
        $item = MediaCoverage::query()->find($this->coverageId);

        if ($item !== null) {
            $checker->check($item);
        }
    }
}
