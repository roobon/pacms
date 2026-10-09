<?php

namespace App\Console\Commands;

use App\Services\Search\SearchIndexer;
use Illuminate\Console\Command;

class RebuildSearchIndexCommand extends Command
{
    protected $signature = 'pacms:search:rebuild';

    protected $description = 'Rebuild the site search index from everything that is published';

    public function handle(SearchIndexer $indexer): int
    {
        $count = $indexer->rebuild();
        $this->components->info("Indexed {$count} page(s) and item(s).");

        return self::SUCCESS;
    }
}
