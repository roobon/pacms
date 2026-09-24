<?php

namespace App\Jobs;

use App\Models\Media;
use App\Services\Media\MediaService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateImageVariants implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public int $mediaId) {}

    public function handle(MediaService $media): void
    {
        $item = Media::query()->find($this->mediaId);

        if ($item !== null) {
            $media->generateVariants($item);
        }
    }
}
