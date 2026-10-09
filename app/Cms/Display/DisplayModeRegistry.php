<?php

namespace App\Cms\Display;

use App\Cms\Validation\ValueValidator;

/**
 * Display modes (CMS-ARCHITECTURE.md §7): presentation of a block's items, independent
 * of where the items come from. Each mode declares its options; blocks declare which
 * modes they support.
 */
final class DisplayModeRegistry
{
    /**
     * @return array<string, array{label: string, options: list<string>}>
     */
    public static function definitions(): array
    {
        return [
            'grid' => ['label' => 'Grid', 'options' => ['columns', 'gap', 'card_style', 'image_ratio']],
            'list' => ['label' => 'List', 'options' => ['gap', 'show_image']],
            'carousel' => ['label' => 'Carousel', 'options' => ['columns', 'gap', 'card_style', 'image_ratio', 'autoplay', 'interval', 'arrows', 'dots']],
            'featured' => ['label' => 'Featured + grid', 'options' => ['columns', 'gap', 'card_style', 'image_ratio']],
            'accordion' => ['label' => 'Accordion', 'options' => ['first_open', 'allow_multiple_open']],
            'masonry' => ['label' => 'Masonry', 'options' => ['columns', 'gap', 'card_style']],
            'quote-slider' => ['label' => 'Quote slider (one at a time)', 'options' => []],
            'single' => ['label' => 'Single (first item only)', 'options' => ['card_style']],
        ];
    }

    /**
     * @param  array<string, mixed>  $display
     * @param  list<string>  $allowedModes
     * @return array<string, mixed>
     */
    public function validate(array $display, array $allowedModes, ValueValidator $values, string $path): array
    {
        if ($allowedModes === []) {
            return [];
        }

        $mode = $values->enum($display['mode'] ?? $allowedModes[0], $allowedModes, "{$path}.mode");
        if ($mode === null) {
            return [];
        }

        $clean = ['mode' => $mode];
        foreach (self::definitions()[$mode]['options'] as $option) {
            if (! array_key_exists($option, $display) || $display[$option] === null || $display[$option] === '') {
                continue;
            }
            $value = $display[$option];
            $p = "{$path}.{$option}";

            $clean[$option] = match ($option) {
                'columns' => $this->columns($value, $values, $p),
                'gap' => $values->token($value, ['space'], $p),
                'card_style' => $values->enum($value, ['elevated', 'outline', 'flat'], $p),
                'image_ratio' => $values->enum($value, ['1:1', '4:3', '3:2', '16:9', '21:9', 'auto'], $p),
                'interval' => max(3000, min(20000, (int) $value)),
                'autoplay', 'arrows', 'dots', 'show_image', 'first_open', 'allow_multiple_open' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
                default => null,
            };
        }

        return array_filter($clean, fn ($v) => $v !== null);
    }

    /**
     * @return array<string, int>|null
     */
    private function columns(mixed $value, ValueValidator $values, string $path): ?array
    {
        if (! is_array($value)) {
            $values->errors()->add($path, __('Invalid columns.'));

            return null;
        }

        $clean = [];
        foreach (['desktop', 'tablet', 'mobile'] as $breakpoint) {
            if (isset($value[$breakpoint])) {
                $count = (int) $value[$breakpoint];
                if ($count < 1 || $count > 6) {
                    $values->errors()->add("{$path}.{$breakpoint}", __('Use 1 to 6 columns.'));

                    continue;
                }
                $clean[$breakpoint] = $count;
            }
        }

        return $clean;
    }
}
