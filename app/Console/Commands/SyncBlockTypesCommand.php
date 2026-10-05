<?php

namespace App\Console\Commands;

use App\Cms\Blocks\BlockRegistry;
use Illuminate\Console\Command;

class SyncBlockTypesCommand extends Command
{
    protected $signature = 'pacms:blocks:sync';

    protected $description = 'Synchronise the core block type definitions into the database (run on every deploy)';

    public function handle(BlockRegistry $registry): int
    {
        $result = $registry->sync();

        $this->components->info("Block types synchronised: {$result['created']} created, {$result['updated']} updated.");

        return self::SUCCESS;
    }
}
