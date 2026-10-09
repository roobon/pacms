<?php

namespace App\Cms\Blocks\Types;

/**
 * Media coverage collection: cards with source, type and date, newest first; filter by
 * category, coverage type, program or project, or featured coverage only.
 */
class MediaCoverageBlock extends ContentCollectionBlock
{
    protected function entity(): string
    {
        return 'media_coverage';
    }

    protected function emptyText(): string
    {
        return 'No media coverage yet.';
    }

    public function slug(): string
    {
        return 'media-coverage';
    }

    public function label(): string
    {
        return 'Media Coverage';
    }

    public function icon(): string
    {
        return 'bi-broadcast';
    }

    public function defaults(): array
    {
        $defaults = parent::defaults();
        $defaults['content']['heading'] = 'In the media';

        return $defaults;
    }
}
