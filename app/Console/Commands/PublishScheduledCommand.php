<?php

namespace App\Console\Commands;

use App\Services\Publishing\PublishingService;
use Illuminate\Console\Command;

class PublishScheduledCommand extends Command
{
    protected $signature = 'pacms:publish-scheduled';

    protected $description = 'Publish approved content whose scheduled publish time has passed';

    public function handle(PublishingService $publishing): int
    {
        $count = $publishing->publishDue();

        if ($count > 0) {
            $this->components->info("Published {$count} scheduled item(s).");
        }

        return self::SUCCESS;
    }
}
