<?php

namespace App\Cms\Blocks\Types;

/**
 * Galleries collection: gallery cards (cover, title, date), newest first.
 */
class GalleriesBlock extends ContentCollectionBlock
{
    protected function entity(): string
    {
        return 'galleries';
    }

    protected function emptyText(): string
    {
        return 'No galleries yet.';
    }

    public function label(): string
    {
        return 'Galleries';
    }

    public function icon(): string
    {
        return 'bi-collection';
    }
}
