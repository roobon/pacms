<?php

namespace App\Cms\Blocks\Types;

/**
 * Programs collection: newest first, or featured programs only.
 */
class ProgramsBlock extends ContentCollectionBlock
{
    protected function entity(): string
    {
        return 'programs';
    }

    protected function emptyText(): string
    {
        return 'No programs yet.';
    }

    public function label(): string
    {
        return 'Programs';
    }

    public function icon(): string
    {
        return 'bi-diagram-3';
    }
}
