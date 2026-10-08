<?php

namespace App\Cms\Blocks\Types;

/**
 * Publications collection: newest publication first; a category, or featured publications only.
 */
class PublicationsBlock extends ContentCollectionBlock
{
    protected function entity(): string
    {
        return 'publications';
    }

    protected function emptyText(): string
    {
        return 'No publications yet.';
    }

    public function label(): string
    {
        return 'Publications';
    }

    public function icon(): string
    {
        return 'bi-journal-richtext';
    }
}
