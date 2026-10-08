<?php

namespace App\Policies;

class EventPolicy extends EditorialPolicy
{
    protected function type(): string
    {
        return 'events';
    }
}
