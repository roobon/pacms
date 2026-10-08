<?php

namespace App\Policies;

class TeamMemberPolicy extends ManagedContentPolicy
{
    protected function permission(): string
    {
        return 'team.manage';
    }
}
