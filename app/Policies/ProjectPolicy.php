<?php

namespace App\Policies;

class ProjectPolicy extends EditorialPolicy
{
    protected function type(): string
    {
        return 'projects';
    }
}
