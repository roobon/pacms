<?php

namespace App\Policies;

class GalleryPolicy extends EditorialPolicy
{
    protected function type(): string
    {
        return 'galleries';
    }
}
