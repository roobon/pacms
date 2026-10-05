<?php

namespace App\Policies;

class NewsPolicy extends EditorialPolicy
{
    protected function type(): string
    {
        return 'news';
    }
}
