<?php

namespace App\Policies;

class MediaCoveragePolicy extends EditorialPolicy
{
    protected function type(): string
    {
        return 'media_coverage';
    }
}
