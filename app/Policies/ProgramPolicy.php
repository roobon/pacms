<?php

namespace App\Policies;

class ProgramPolicy extends EditorialPolicy
{
    protected function type(): string
    {
        return 'programs';
    }
}
