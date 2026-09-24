<?php

namespace App\Support\Color;

use InvalidArgumentException;

/**
 * Minimal sRGB colour helper for design tokens: parsing, WCAG 2.x contrast and readable text colour.
 */
final class Color
{
    private function __construct(
        public readonly int $r,
        public readonly int $g,
        public readonly int $b,
    ) {}

    public static function isHex(string $value): bool
    {
        return (bool) preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $value);
    }

    public static function fromHex(string $hex): self
    {
        if (! self::isHex($hex)) {
            throw new InvalidArgumentException("Invalid hex colour [{$hex}].");
        }

        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        return new self(hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)));
    }

    public function toHex(): string
    {
        return sprintf('#%02X%02X%02X', $this->r, $this->g, $this->b);
    }

    /** "R G B" triplet for use in rgb(var(--x) / alpha). */
    public function toRgbTriplet(): string
    {
        return "{$this->r}, {$this->g}, {$this->b}";
    }

    public function relativeLuminance(): float
    {
        $channel = function (int $value): float {
            $c = $value / 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };

        return 0.2126 * $channel($this->r) + 0.7152 * $channel($this->g) + 0.0722 * $channel($this->b);
    }

    public function contrastWith(self $other): float
    {
        $a = $this->relativeLuminance();
        $b = $other->relativeLuminance();

        return (max($a, $b) + 0.05) / (min($a, $b) + 0.05);
    }

    /**
     * The more readable of the two candidate text colours on this background.
     */
    public function readableText(self $dark, self $light): self
    {
        return $this->contrastWith($dark) >= $this->contrastWith($light) ? $dark : $light;
    }
}
