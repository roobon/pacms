<?php

namespace App\Observers;

use App\Services\Search\SearchIndexer;
use Illuminate\Database\Eloquent\Model;

/**
 * Updates the search index whenever a page or content item is saved, published, unpublished
 * or deleted (CMS-ARCHITECTURE.md §21). Runs after the transaction commits, so the index
 * sees the blocks and SEO saved with it.
 */
class SearchObserver
{
    public bool $afterCommit = true;

    public function __construct(private readonly SearchIndexer $indexer) {}

    public function saved(Model $model): void
    {
        $this->indexer->sync($model);
    }

    public function deleted(Model $model): void
    {
        $this->indexer->remove($model);
    }

    public function restored(Model $model): void
    {
        $this->indexer->sync($model);
    }
}
