<?php

namespace App\Services\Media;

use App\Enums\MediaKind;
use finfo;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Validates an uploaded file before it is stored (SECURITY-ARCHITECTURE.md §8):
 * extension allowlist, MIME detected from the content, no dangerous name parts,
 * size limits per kind, and image dimension limits (decompression-bomb guard).
 */
class UploadGuard
{
    /** Name parts that are never accepted anywhere in a filename (e.g. "photo.php.jpg"). */
    private const DANGEROUS_PARTS = [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'pht', 'phps', 'phar', 'inc',
        'js', 'mjs', 'html', 'htm', 'xhtml', 'shtml', 'svg', 'svgz', 'xml', 'exe', 'dll', 'bat', 'cmd',
        'com', 'sh', 'cgi', 'pl', 'py', 'rb', 'asp', 'aspx', 'jsp', 'htaccess', 'htpasswd',
    ];

    /**
     * @return array{extension: string, mime: string, kind: MediaKind, width: ?int, height: ?int}
     *
     * @throws ValidationException
     */
    public function inspect(UploadedFile $file, string $field = 'file', bool $allowSvg = false): array
    {
        if (! $file->isValid()) {
            $this->fail($field, __('The upload failed. The file may be larger than the server allows.'));
        }

        $name = mb_strtolower($file->getClientOriginalName());
        $parts = explode('.', $name);
        $extension = (string) array_pop($parts);

        foreach ($parts as $part) {
            if (in_array($part, self::DANGEROUS_PARTS, true)) {
                $this->fail($field, __('This file name is not allowed.'));
            }
        }

        if ($extension === 'svg') {
            return $this->svg($file, $field, $allowSvg);
        }

        $allowed = config('pacms.media.allowed');
        if (! isset($allowed[$extension])) {
            $this->fail($field, __('Files of type .:ext are not allowed.', ['ext' => $extension]));
        }

        [$kind, $mimes] = $allowed[$extension];
        $kind = MediaKind::from($kind);

        $detected = (string) (new finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath());
        if (! in_array($detected, $mimes, true)) {
            $this->fail($field, __('The file content does not match its .:ext extension.', ['ext' => $extension]));
        }

        $maxKb = (int) config('pacms.media.max_kb.'.$kind->value);
        if ($file->getSize() > $maxKb * 1024) {
            $this->fail($field, __(':kind files may be at most :size MB.', ['kind' => $kind->label(), 'size' => round($maxKb / 1024)]));
        }

        $width = $height = null;
        if ($kind === MediaKind::Image) {
            $dimensions = @getimagesize($file->getRealPath());
            if ($dimensions === false) {
                $this->fail($field, __('This image could not be read.'));
            }
            [$width, $height] = $dimensions;

            $maxDimension = (int) config('pacms.media.max_dimension');
            $megapixels = ($width * $height) / 1_000_000;
            if ($width > $maxDimension || $height > $maxDimension || $megapixels > (int) config('pacms.media.max_megapixels')) {
                $this->fail($field, __('The image is too large (maximum :dim px per side and :mp megapixels).', [
                    'dim' => $maxDimension, 'mp' => config('pacms.media.max_megapixels'),
                ]));
            }
        }

        return ['extension' => $extension === 'jpeg' ? 'jpg' : $extension, 'mime' => $detected, 'kind' => $kind, 'width' => $width, 'height' => $height];
    }

    /**
     * SVG (decision D-10): only when the site allows it and the user may upload SVG. The
     * file is cleaned afterwards by SvgSanitizer before it is stored.
     *
     * @return array{extension: string, mime: string, kind: MediaKind, width: ?int, height: ?int}
     */
    private function svg(UploadedFile $file, string $field, bool $allowed): array
    {
        if (! $allowed) {
            $this->fail($field, __('SVG uploads are switched off. An administrator can allow them under Settings → Media.'));
        }

        $detected = (string) (new finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath());
        if (! in_array($detected, ['image/svg+xml', 'image/svg', 'text/xml', 'application/xml', 'text/plain', 'text/html'], true)) {
            $this->fail($field, __('The file content does not match its .:ext extension.', ['ext' => 'svg']));
        }

        if ($file->getSize() > SvgSanitizer::MAX_KB * 1024) {
            $this->fail($field, __('SVG files may be at most :size MB.', ['size' => SvgSanitizer::MAX_KB / 1024]));
        }

        return ['extension' => 'svg', 'mime' => 'image/svg+xml', 'kind' => MediaKind::Image, 'width' => null, 'height' => null];
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
