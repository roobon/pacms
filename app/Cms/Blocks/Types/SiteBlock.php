<?php

namespace App\Cms\Blocks\Types;

use App\Cms\Blocks\BlockType;

/**
 * Header and footer building blocks (Phase 9): they show site settings (logo, menus,
 * contact details, social profiles), so they live in the "Site" group of the palette.
 */
abstract class SiteBlock extends BlockType
{
    public function category(): string
    {
        return 'site';
    }
}
