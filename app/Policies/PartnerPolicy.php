<?php

namespace App\Policies;

class PartnerPolicy extends ManagedContentPolicy
{
    protected function permission(): string
    {
        return 'partners.manage';
    }
}
