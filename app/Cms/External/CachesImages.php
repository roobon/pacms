<?php

namespace App\Cms\External;

use App\Models\ExternalSource;

/**
 * A provider whose image addresses expire (Facebook): after each sync, new item images are
 * saved into the Media Library so blocks never show broken images.
 */
interface CachesImages
{
    public function cacheImages(ExternalSource $source): void;
}
