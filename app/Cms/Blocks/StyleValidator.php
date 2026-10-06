<?php

namespace App\Cms\Blocks;

use App\Cms\Validation\ValueValidator;
use App\Enums\MediaKind;

/**
 * Validates a block's layout, style, responsive and advanced settings
 * (CMS-ARCHITECTURE.md §8, CMS-BLOCK-SCHEMA.md §12–15).
 *
 * Every value is a design-token reference or a validated literal, so the style compiler
 * can never receive free-form CSS. Unknown keys are dropped.
 */
final class StyleValidator
{
    public const BREAKPOINTS = ['desktop', 'tablet', 'mobile'];

    private const SIDES = ['top', 'right', 'bottom', 'left'];

    public function __construct(private readonly ValueValidator $values) {}

    /**
     * @param  array<string, mixed>  $layout
     * @return array<string, mixed>
     */
    public function layout(array $layout, string $path): array
    {
        $clean = [];

        foreach ($layout as $key => $value) {
            $p = "{$path}.{$key}";
            $clean[$key] = match ($key) {
                'container' => $this->values->enum($value, ['boxed', 'narrow', 'fluid', 'full'], $p),
                'max_width' => is_array($value) && isset($value['$token'])
                    ? $this->values->token($value, ['container'], $p)
                    : $this->values->length($value, $p, ['px', 'rem', '%', 'vw'], 0, 3000),
                'min_height' => $this->values->length($value, $p, ['px', 'rem', 'vh'], 0, 2000),
                'columns' => $this->columns($value, $p),
                'align' => $this->values->enum($value, ['start', 'center', 'end', 'stretch'], $p),
                'justify' => $this->values->enum($value, ['start', 'center', 'end', 'between', 'around', 'evenly'], $p),
                'text_align' => $this->values->enum($value, ['start', 'center', 'end'], $p),
                'gap' => $this->values->spacing($value, $p),
                'padding' => $this->sides($value, $p, 0),
                'margin' => $this->sides($value, $p, -200),
                default => null,
            };
        }

        return array_filter($clean, fn ($v) => $v !== null && $v !== []);
    }

    /**
     * @param  array<string, mixed>  $style
     * @return array<string, mixed>
     */
    public function style(array $style, string $path): array
    {
        $clean = [];

        foreach ($style as $key => $value) {
            $p = "{$path}.{$key}";
            $clean[$key] = match ($key) {
                'background' => $this->background($value, $p),
                'typography' => $this->typography($value, $p),
                'border' => $this->border($value, $p),
                'radius' => $this->values->token($value, ['radius'], $p),
                'shadow' => $this->values->token($value, ['shadow'], $p),
                'animation' => is_array($value)
                    ? array_filter(['type' => $this->values->enum($value['type'] ?? 'none', ['none', 'fade', 'fade-up', 'fade-down', 'slide-left', 'slide-right', 'zoom'], "{$p}.type")])
                    : null,
                default => null,
            };
        }

        return array_filter($clean, fn ($v) => $v !== null && $v !== []);
    }

    /**
     * Tablet/mobile overrides: same rules as the base values, plus visibility.
     *
     * @param  array<string, mixed>  $responsive
     * @return array<string, mixed>
     */
    public function responsive(array $responsive, string $path): array
    {
        $clean = [];

        foreach (['tablet', 'mobile'] as $breakpoint) {
            $values = $responsive[$breakpoint] ?? null;
            if (! is_array($values)) {
                continue;
            }

            $p = "{$path}.{$breakpoint}";
            $clean[$breakpoint] = array_filter([
                'layout' => is_array($values['layout'] ?? null) ? $this->layout($values['layout'], "{$p}.layout") : null,
                'style' => is_array($values['style'] ?? null) ? $this->style($values['style'], "{$p}.style") : null,
            ]);
        }

        return array_filter($clean);
    }

    /**
     * @param  array<string, mixed>  $advanced
     * @return array<string, mixed>
     */
    public function advanced(array $advanced, string $path, bool $mayUseAttributes): array
    {
        $errors = $this->values->errors();
        $clean = [];

        if (isset($advanced['anchor']) && $advanced['anchor'] !== '') {
            if (is_string($advanced['anchor']) && preg_match('/^[a-z][a-z0-9-]{0,63}$/', $advanced['anchor'])) {
                $clean['anchor'] = $advanced['anchor'];
            } else {
                $errors->add("{$path}.anchor", __('Use lowercase letters, numbers and hyphens, starting with a letter.'));
            }
        }

        if (isset($advanced['classes']) && $advanced['classes'] !== []) {
            $classes = is_array($advanced['classes']) ? $advanced['classes'] : preg_split('/\s+/', (string) $advanced['classes']);
            $valid = array_values(array_filter((array) $classes, fn ($c) => is_string($c) && preg_match('/^[a-z][a-z0-9_-]{0,63}$/i', $c)));
            if (count($valid) !== count(array_filter((array) $classes, fn ($c) => $c !== '')) || count($valid) > 10) {
                $errors->add("{$path}.classes", __('Up to 10 CSS class names using letters, numbers, - and _.'));
            } else {
                $clean['classes'] = $valid;
            }
        }

        if (isset($advanced['attributes']) && $advanced['attributes'] !== []) {
            if (! $mayUseAttributes) {
                $errors->add("{$path}.attributes", __('You are not allowed to set custom attributes.'));
            } else {
                $clean['attributes'] = $this->attributes($advanced['attributes'], "{$path}.attributes");
            }
        }

        $hideOn = $advanced['visibility']['hide_on'] ?? [];
        if ($hideOn !== []) {
            $hideOn = array_values(array_intersect((array) $hideOn, self::BREAKPOINTS));
            if (count($hideOn) === count(self::BREAKPOINTS)) {
                $errors->add("{$path}.visibility.hide_on", __('A block cannot be hidden on every device. Hide the block instead.'));
            } elseif ($hideOn !== []) {
                $clean['visibility'] = ['hide_on' => $hideOn];
            }
        }

        return $clean;
    }

    /**
     * @return array<string, list<int>>|null
     */
    private function columns(mixed $value, string $path): ?array
    {
        if (! is_array($value)) {
            $this->values->errors()->add($path, __('Invalid column layout.'));

            return null;
        }

        $clean = [];
        foreach (self::BREAKPOINTS as $breakpoint) {
            $spans = $value[$breakpoint] ?? null;
            if ($spans === null) {
                continue;
            }
            if (! is_array($spans) || $spans === [] || count($spans) > 6) {
                $this->values->errors()->add("{$path}.{$breakpoint}", __('Use 1 to 6 columns.'));

                continue;
            }
            $spans = array_map('intval', array_values($spans));
            foreach ($spans as $span) {
                if ($span < 1 || $span > 12) {
                    $this->values->errors()->add("{$path}.{$breakpoint}", __('Column widths are 1 to 12 (out of 12).'));

                    continue 2;
                }
            }
            $clean[$breakpoint] = $spans;
        }

        return $clean;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function sides(mixed $value, string $path, float $min): ?array
    {
        if (! is_array($value)) {
            $this->values->errors()->add($path, __('Invalid spacing.'));

            return null;
        }

        $clean = [];
        foreach (self::SIDES as $side) {
            if (isset($value[$side]) && $value[$side] !== '') {
                $clean[$side] = $this->values->spacing($value[$side], "{$path}.{$side}", $min);
            }
        }

        return array_filter($clean);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function background(mixed $value, string $path): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $type = $this->values->enum($value['type'] ?? 'none', ['none', 'color', 'gradient', 'image'], "{$path}.type");
        $clean = ['type' => $type];

        if ($type === 'color') {
            $clean['color'] = $this->values->color($value['color'] ?? null, "{$path}.color");
        }

        if ($type === 'gradient') {
            $stops = $value['gradient']['stops'] ?? [];
            if (! is_array($stops) || count($stops) < 2 || count($stops) > 4) {
                $this->values->errors()->add("{$path}.gradient", __('Use 2 to 4 gradient colours.'));
            } else {
                $clean['gradient'] = [
                    'angle' => max(0, min(360, (int) ($value['gradient']['angle'] ?? 135))),
                    'stops' => array_map(fn ($stop, $i) => [
                        'color' => $this->values->color($stop['color'] ?? null, "{$path}.gradient.stops.{$i}.color"),
                        'at' => max(0, min(100, (int) ($stop['at'] ?? 0))),
                    ], array_values($stops), array_keys(array_values($stops))),
                ];
            }
        }

        if ($type === 'image') {
            $clean['image'] = $this->values->mediaRef($value['image'] ?? null, "{$path}.image", MediaKind::Image);
            $clean['position'] = $this->values->enum($value['position'] ?? 'center', ['center', 'top', 'bottom', 'left', 'right'], "{$path}.position");
            $clean['size'] = $this->values->enum($value['size'] ?? 'cover', ['cover', 'contain', 'auto'], "{$path}.size");
        }

        if (isset($value['overlay']) && is_array($value['overlay']) && ($value['overlay']['color'] ?? null)) {
            $clean['overlay'] = [
                'color' => $this->values->color($value['overlay']['color'], "{$path}.overlay.color"),
                'opacity' => max(0, min(1, round((float) ($value['overlay']['opacity'] ?? 0.5), 2))),
            ];
        }

        return $type === 'none' && ! isset($clean['overlay']) ? null : array_filter($clean, fn ($v) => $v !== null);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function typography(mixed $value, string $path): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $clean = [];
        foreach ($value as $key => $v) {
            if ($v === null || $v === '') {
                continue;
            }
            $p = "{$path}.{$key}";
            $clean[$key] = match ($key) {
                'color' => $this->values->color($v, $p),
                'font' => $this->values->token($v, ['font'], $p),
                'size' => $this->values->token($v, ['font-size'], $p),
                'weight' => in_array((int) $v, [300, 400, 500, 600, 700, 800, 900], true) ? (int) $v : $this->invalid($p),
                'align' => $this->values->enum($v, ['start', 'center', 'end'], $p),
                'transform' => $this->values->enum($v, ['none', 'uppercase', 'lowercase', 'capitalize'], $p),
                default => null,
            };
        }

        return array_filter($clean, fn ($v) => $v !== null);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function border(mixed $value, string $path): ?array
    {
        if (! is_array($value) || empty($value['width'])) {
            return null;
        }

        return array_filter([
            'width' => $this->values->length($value['width'], "{$path}.width", ['px'], 0, 20),
            'style' => $this->values->enum($value['style'] ?? 'solid', ['solid', 'dashed', 'dotted'], "{$path}.style"),
            'color' => $this->values->color($value['color'] ?? ['$token' => 'color.border'], "{$path}.color"),
            'sides' => array_values(array_intersect((array) ($value['sides'] ?? self::SIDES), self::SIDES)),
        ]);
    }

    /**
     * Only data-*, aria-*, role, title and lang — never event handlers, style, href or src.
     *
     * @return array<string, string>
     */
    private function attributes(mixed $value, string $path): array
    {
        $clean = [];

        foreach ((array) $value as $name => $attributeValue) {
            $name = strtolower((string) $name);
            $allowed = preg_match('/^(data|aria)-[a-z0-9-]{1,40}$/', $name) || in_array($name, ['role', 'title', 'lang'], true);

            if (! $allowed || ! is_scalar($attributeValue)) {
                $this->values->errors()->add("{$path}.{$name}", __('The attribute ":name" is not allowed.', ['name' => $name]));

                continue;
            }

            $clean[$name] = mb_substr((string) $attributeValue, 0, 255);
        }

        return $clean;
    }

    private function invalid(string $path): null
    {
        $this->values->errors()->add($path, __('Invalid value.'));

        return null;
    }
}
