<?php

namespace App\Policies;

class PagePolicy extends EditorialPolicy
{
    protected function type(): string
    {
        return 'pages';
    }
}
