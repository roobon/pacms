<?php

namespace App\Cms\Design;

/**
 * The design-token catalogue (UI-DESIGN-SYSTEM.md §2).
 *
 * Token names are what blocks and JSON reference ({"$token": "color.primary"}).
 * The CSS custom property is "--pa-" + name with "." replaced by "-".
 *
 * Types:
 *  - color:  editable, #RRGGBB
 *  - font:   editable, chosen from FONT_STACKS
 *  - length: editable, number + px|rem
 *  - raw:    fixed in v1 (clamp() scales, shadows, easing); shipped as defaults only
 */
final class TokenCatalog
{
    public const FONT_STACKS = [
        'inter' => ['label' => 'Inter', 'stack' => '"Inter Variable", "Inter", system-ui, -apple-system, "Segoe UI", sans-serif'],
        'jakarta' => ['label' => 'Plus Jakarta Sans', 'stack' => '"Plus Jakarta Sans Variable", "Plus Jakarta Sans", system-ui, -apple-system, "Segoe UI", sans-serif'],
        'system' => ['label' => 'System UI', 'stack' => 'system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif'],
        'serif' => ['label' => 'Serif (Georgia)', 'stack' => 'Georgia, "Times New Roman", serif'],
    ];

    /**
     * @return array<string, array{type: string, group: string, label: string, default: string}>
     */
    public static function definitions(): array
    {
        $color = fn (string $label, string $default) => ['type' => 'color', 'group' => 'Colours', 'label' => $label, 'default' => $default];
        $raw = fn (string $group, string $default, string $label = '') => ['type' => 'raw', 'group' => $group, 'label' => $label, 'default' => $default];
        $length = fn (string $group, string $label, string $default) => ['type' => 'length', 'group' => $group, 'label' => $label, 'default' => $default];

        return [
            'color.primary' => $color('Primary', '#0A6B66'),
            'color.primary-strong' => $color('Primary (hover/pressed)', '#08564F'),
            'color.secondary' => $color('Secondary', '#23305E'),
            'color.accent' => $color('Accent', '#F2A93B'),
            'color.heading' => $color('Heading text', '#0F1B2D'),
            'color.body' => $color('Body text', '#334155'),
            'color.muted' => $color('Muted text', '#5B6B7F'),
            'color.bg-light' => $color('Light background', '#F4F7F8'),
            'color.bg-dark' => $color('Dark background', '#0B1628'),
            'color.border' => $color('Border (decorative)', '#D5DEE4'),
            'color.border-strong' => $color('Form control border', '#7D8EA3'),
            'color.success' => $color('Success', '#1B7A4B'),
            'color.warning' => $color('Warning', '#F4B740'),
            'color.danger' => $color('Danger', '#B8322A'),
            'color.focus' => $color('Focus ring', '#1B64D1'),
            'color.white' => $color('White', '#FFFFFF'),

            'font.heading' => ['type' => 'font', 'group' => 'Typography', 'label' => 'Heading font', 'default' => 'jakarta'],
            'font.body' => ['type' => 'font', 'group' => 'Typography', 'label' => 'Body font', 'default' => 'inter'],

            'font-size.xs' => $raw('Typography', '0.75rem'),
            'font-size.sm' => $raw('Typography', '0.875rem'),
            'font-size.base' => $raw('Typography', 'clamp(1rem, 0.98rem + 0.1vw, 1.0625rem)'),
            'font-size.lg' => $raw('Typography', 'clamp(1.125rem, 1.08rem + 0.2vw, 1.25rem)'),
            'font-size.xl' => $raw('Typography', 'clamp(1.25rem, 1.17rem + 0.35vw, 1.5rem)'),
            'font-size.2xl' => $raw('Typography', 'clamp(1.5rem, 1.36rem + 0.6vw, 1.875rem)'),
            'font-size.3xl' => $raw('Typography', 'clamp(1.875rem, 1.69rem + 0.8vw, 2.375rem)'),
            'font-size.4xl' => $raw('Typography', 'clamp(2.25rem, 1.97rem + 1.2vw, 3rem)'),
            'font-size.5xl' => $raw('Typography', 'clamp(2.75rem, 2.28rem + 2vw, 4rem)'),

            'space.0' => $raw('Spacing', '0'),
            'space.1' => $raw('Spacing', '0.25rem'),
            'space.2' => $raw('Spacing', '0.5rem'),
            'space.3' => $raw('Spacing', '0.75rem'),
            'space.4' => $raw('Spacing', '1rem'),
            'space.5' => $raw('Spacing', '1.5rem'),
            'space.6' => $raw('Spacing', '2rem'),
            'space.7' => $raw('Spacing', '3rem'),
            'space.8' => $raw('Spacing', '4rem'),
            'space.9' => $raw('Spacing', '6rem'),
            'space.10' => $raw('Spacing', '8rem'),
            'space.section' => $raw('Spacing', 'clamp(3rem, 6vw, 6rem)'),

            // Same names as a section's "Content width".
            'container.narrow' => $raw('Layout', '760px', 'Narrow'),
            'container.xxl' => $raw('Layout', '1280px', 'Site width'),
            'container.wide' => $raw('Layout', '1440px', 'Wide'),

            'radius.sm' => $length('Shape', 'Small radius', '0.375rem'),
            'radius.md' => $length('Shape', 'Medium radius', '0.625rem'),
            'radius.lg' => $length('Shape', 'Large radius', '1rem'),
            'radius.xl' => $raw('Shape', '1.5rem'),
            'radius.pill' => $raw('Shape', '999px'),

            'shadow.sm' => $raw('Depth', '0 1px 2px rgb(15 27 45 / .06), 0 1px 3px rgb(15 27 45 / .08)'),
            'shadow.md' => $raw('Depth', '0 4px 12px rgb(15 27 45 / .08)'),
            'shadow.lg' => $raw('Depth', '0 12px 32px rgb(15 27 45 / .12)'),

            'motion.duration-fast' => $raw('Motion', '120ms'),
            'motion.duration-base' => $raw('Motion', '200ms'),
            'motion.duration-slow' => $raw('Motion', '320ms'),
            'motion.easing' => $raw('Motion', 'cubic-bezier(.2, .7, .2, 1)'),

            'button.radius' => $length('Buttons', 'Button radius', '0.625rem'),
            'button.font-weight' => $raw('Buttons', '600'),
            'form.radius' => $length('Forms', 'Form control radius', '0.375rem'),
        ];
    }

    /**
     * Text/background pairs checked for WCAG contrast when tokens are saved.
     * [foreground, background, minimum ratio, description]
     *
     * @return list<array{0: string, 1: string, 2: float, 3: string}>
     */
    public static function contrastPairs(): array
    {
        return [
            ['color.body', 'color.white', 4.5, 'Body text on white'],
            ['color.body', 'color.bg-light', 4.5, 'Body text on light background'],
            ['color.heading', 'color.white', 4.5, 'Headings on white'],
            ['color.muted', 'color.white', 4.5, 'Muted text on white'],
            ['color.muted', 'color.bg-light', 4.5, 'Muted text on light background'],
            ['color.primary', 'color.white', 4.5, 'Links on white'],
            ['color.white', 'color.bg-dark', 4.5, 'White text on dark background'],
            ['color.border-strong', 'color.white', 3.0, 'Form control borders on white'],
            ['color.focus', 'color.white', 3.0, 'Focus ring on white'],
        ];
    }

    public static function cssVariable(string $token): string
    {
        return '--pa-'.str_replace('.', '-', $token);
    }
}
