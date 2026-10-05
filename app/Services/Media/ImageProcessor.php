<?php

namespace App\Services\Media;

use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Format;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Interfaces\ImageManagerInterface;

/**
 * Image re-encoding and variants (Intervention Image 4, GD).
 *
 * Re-encoding every upload applies the EXIF orientation, then strips all metadata
 * (including GPS location) and neutralises polyglot payloads hidden in image files.
 * Animated GIFs are flattened to their first frame.
 */
class ImageProcessor
{
    private ImageManagerInterface $manager;

    public function __construct()
    {
        $this->manager = ImageManager::usingDriver(GdDriver::class, autoOrientation: true, decodeAnimation: false, strip: true);
    }

    public function decode(string $binary): ImageInterface
    {
        return $this->manager->decodeBinary($binary);
    }

    /**
     * Re-encode in the original format.
     */
    public function reencode(ImageInterface $image, string $extension): string
    {
        $format = Format::create($extension === 'jpg' ? 'jpeg' : $extension);
        $quality = (int) config('pacms.media.quality');

        return (string) match ($format) {
            Format::JPEG => $image->encodeUsingFormat($format, quality: $quality, progressive: true),
            Format::WEBP, Format::AVIF => $image->encodeUsingFormat($format, quality: $quality),
            default => $image->encodeUsingFormat($format),
        };
    }

    public function webpVariant(ImageInterface $image, int $width): string
    {
        return (string) (clone $image)->scaleDown(width: $width)->encodeUsingFormat(Format::WEBP, quality: (int) config('pacms.media.quality'));
    }

    /**
     * Tiny blurred-up placeholder as a data URI (shown while the real image loads).
     */
    public function placeholder(ImageInterface $image): string
    {
        $encoded = (clone $image)->scaleDown(width: 24)->encodeUsingFormat(Format::WEBP, quality: 40);

        return 'data:image/webp;base64,'.base64_encode((string) $encoded);
    }
}
