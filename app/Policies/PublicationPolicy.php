<?php

namespace App\Policies;

class PublicationPolicy extends EditorialPolicy
{
    protected function type(): string
    {
        return 'publications';
    }
}
