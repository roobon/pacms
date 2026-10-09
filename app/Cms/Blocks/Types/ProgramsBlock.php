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

    public function defaults(): array
    {
        $defaults = parent::defaults();
        // Programmes are ongoing: the date they were put on the website means nothing to visitors.
        $defaults['content']['show_date'] = false;

        return $defaults;
    }
}
