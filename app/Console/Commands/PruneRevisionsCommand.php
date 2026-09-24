<?php

namespace App\Console\Commands;

use App\Services\Revisions\RevisionService;
use Illuminate\Console\Command;

class PruneRevisionsCommand extends Command
{
    protected $signature = 'pacms:revisions:prune';

    protected $description = 'Delete old non-published revisions beyond the retention limit (published revisions are kept)';

    public function handle(RevisionService $revisions): int
    {
        $deleted = $revisions->prune((int) config('pacms.revisions.keep'));

        $this->components->info("Deleted {$deleted} old revision(s).");

        return self::SUCCESS;
    }
}
