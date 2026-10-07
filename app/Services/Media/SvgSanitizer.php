<?php

namespace App\Services\Media;

use enshrined\svgSanitize\Sanitizer;
use Illuminate\Validation\ValidationException;

/**
 * SVG cleaning (SECURITY-ARCHITECTURE.md §8, decision D-10): scripts, event handlers,
 * external references and foreignObject are removed by enshrined/svg-sanitize; the result
 * is checked again so nothing executable is ever stored.
 */
class SvgSanitizer
{
    public const MAX_KB = 1024;

    /**
     * @return array{svg: string, width: int|null, height: int|null}
     *
     * @throws ValidationException
     */
    public function clean(string $svg, string $field = 'file'): array
    {
        $sanitizer = new Sanitizer;
        $sanitizer->removeRemoteReferences(true);
        $sanitizer->removeXMLTag(true);
        $sanitizer->minify(true);

        $clean = $sanitizer->sanitize($svg);

        if (! is_string($clean) || trim($clean) === '' || ! preg_match('/<svg[\s>]/i', $clean)) {
            throw ValidationException::withMessages([$field => __('This SVG could not be read safely.')]);
        }

        // Defence in depth: nothing script-like may survive.
        if (preg_match('/<\s*(script|foreignObject|iframe|embed|object)\b|\bon[a-z]+\s*=|javascript:|data:text\/html/i', $clean)) {
            throw ValidationException::withMessages([$field => __('This SVG contains content that is not allowed.')]);
        }

        return ['svg' => $clean] + $this->dimensions($clean);
    }

    /**
     * Width and height from the root element (attributes, else the viewBox).
     *
     * @return array{width: int|null, height: int|null}
     */
    private function dimensions(string $svg): array
    {
        if (! preg_match('/<svg\b[^>]*>/i', $svg, $root)) {
            return ['width' => null, 'height' => null];
        }

        $attr = fn (string $name) => preg_match('/\b'.$name.'\s*=\s*["\']\s*([0-9.]+)\s*(px)?\s*["\']/i', $root[0], $m) ? (int) round((float) $m[1]) : null;
        $width = $attr('width');
        $height = $attr('height');

        if (($width === null || $height === null) && preg_match('/viewBox\s*=\s*["\']\s*[-0-9.]+[\s,]+[-0-9.]+[\s,]+([0-9.]+)[\s,]+([0-9.]+)\s*["\']/i', $root[0], $box)) {
            $width ??= (int) round((float) $box[1]);
            $height ??= (int) round((float) $box[2]);
        }

        return ['width' => $width ?: null, 'height' => $height ?: null];
    }
}
