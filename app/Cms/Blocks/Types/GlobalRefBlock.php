<?php

namespace App\Cms\Blocks\Types;

use App\Cms\Blocks\BlockType;

/**
 * Places a Global Block (CMS-ARCHITECTURE.md §12). The node stores only
 * `global_block_id`; the published tree of the global block is rendered in its place, so
 * editing the global block changes every page that uses it.
 */
class GlobalRefBlock extends BlockType
{
    public function slug(): string
    {
        return 'global-ref';
    }

    public function label(): string
    {
        return 'Global block';
    }

    public function description(): string
    {
        return 'Shows a shared global block. Edit it under Design → Global blocks.';
    }

    public function category(): string
    {
        return 'structural';
    }

    public function icon(): string
    {
        return 'bi-globe2';
    }

    /** Never inside a global block (no cycles) or a custom block structure. */
    public function contexts(): ?array
    {
        return ['page', 'template'];
    }
}
