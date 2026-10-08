<?php

namespace App\Cms\Blocks\Types;

/**
 * Projects collection: newest first; a project status, or featured projects only, through the source filters.
 */
class ProjectsBlock extends ContentCollectionBlock
{
    protected function entity(): string
    {
        return 'projects';
    }

    protected function emptyText(): string
    {
        return 'No projects yet.';
    }

    public function label(): string
    {
        return 'Projects';
    }

    public function icon(): string
    {
        return 'bi-kanban';
    }
}
