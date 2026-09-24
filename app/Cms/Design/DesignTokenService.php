<?php

namespace App\Cms\Design;

use App\Models\User;
use App\Services\Settings\SettingsService;
use App\Support\Color\Color;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Runtime design tokens (CMS-ARCHITECTURE.md §10).
 *
 * Tokens are stored in settings (design.tokens, overrides only) and compiled into a small
 * CSS custom-property stylesheet on the public disk. Branding changes therefore need no
 * frontend rebuild and no Node.js on the server.
 */
class DesignTokenService
{
    /** Filled-colour tokens that get an automatic readable "on-*" text colour. */
    private const FILLS = ['primary', 'primary-strong', 'secondary', 'accent', 'success', 'warning', 'danger', 'bg-dark'];

    public function __construct(private readonly SettingsService $settings) {}

    /**
     * Effective token values (defaults merged with saved overrides).
     *
     * @return array<string, string>
     */
    public function values(): array
    {
        $values = array_map(fn (array $definition) => $definition['default'], TokenCatalog::definitions());
        $overrides = (array) $this->settings->get('design', 'tokens', []);

        return array_replace($values, array_intersect_key($overrides, $values));
    }

    /**
     * Validate, store and publish new token values. Only editable tokens are accepted.
     *
     * @param  array<string, string>  $input
     * @return list<string> contrast warnings for the saved palette
     */
    public function save(array $input, ?User $user = null): array
    {
        $definitions = TokenCatalog::definitions();
        $errors = [];
        $overrides = [];

        foreach ($input as $token => $value) {
            $definition = $definitions[$token] ?? null;
            if ($definition === null || $definition['type'] === 'raw') {
                $errors[$token] = 'This token cannot be changed.';

                continue;
            }

            $value = trim((string) $value);
            if (! $this->isValid($definition['type'], $value)) {
                $errors[$token] = "Invalid value for {$definition['label']}.";

                continue;
            }

            if ($value !== $definition['default']) {
                $overrides[$token] = $definition['type'] === 'color' ? strtoupper($value) : $value;
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages(
                collect($errors)->mapWithKeys(fn ($message, $token) => ['tokens.'.$token => $message])->all()
            );
        }

        $this->settings->set('design', ['tokens' => $overrides], $user);
        $this->publish($user);

        return $this->contrastWarnings();
    }

    /**
     * Compile the stylesheet, write it with a content hash in the filename and record its path.
     */
    public function publish(?User $user = null): string
    {
        $css = $this->compile();
        $path = trim(config('pacms.theme.directory'), '/').'/tokens-'.substr(hash('sha256', $css), 0, 12).'.css';

        $disk = Storage::disk('public');
        if (! $disk->exists($path)) {
            $disk->put($path, $css);
        }

        $previous = $this->settings->get('design', 'stylesheet');
        if ($previous !== $path) {
            $this->settings->set('design', ['stylesheet' => $path], $user);
            if ($previous && $disk->exists($previous)) {
                $disk->delete($previous);
            }
        }

        return $path;
    }

    /**
     * Public URL of the current token stylesheet, generating it on first use.
     */
    public function stylesheetUrl(): string
    {
        $path = $this->settings->get('design', 'stylesheet');

        if (! $path || ! Storage::disk('public')->exists($path)) {
            $path = $this->publish();
        }

        $url = Storage::disk('public')->url($path);

        // Same-site files are linked root-relative so the stylesheet loads on whatever host
        // the site is opened with (e.g. localhost vs. APP_URL); a CDN/absolute disk URL is kept.
        $appUrl = rtrim((string) config('app.url'), '/');

        return $appUrl !== '' && str_starts_with($url, $appUrl.'/') ? substr($url, strlen($appUrl)) : $url;
    }

    public function compile(): string
    {
        $values = $this->values();
        $definitions = TokenCatalog::definitions();
        $lines = [];

        foreach ($values as $token => $value) {
            $css = $definitions[$token]['type'] === 'font' ? TokenCatalog::FONT_STACKS[$value]['stack'] : $value;
            $lines[] = '  '.TokenCatalog::cssVariable($token).': '.$css.';';

            if ($definitions[$token]['type'] === 'color') {
                $lines[] = '  '.TokenCatalog::cssVariable($token).'-rgb: '.Color::fromHex($value)->toRgbTriplet().';';
            }
        }

        $dark = Color::fromHex($values['color.heading']);
        $light = Color::fromHex($values['color.white']);
        foreach (self::FILLS as $fill) {
            $on = Color::fromHex($values["color.{$fill}"])->readableText($dark, $light);
            $lines[] = "  --pa-color-on-{$fill}: {$on->toHex()};";
        }

        return "/* Probha Aurora CMS design tokens — generated, do not edit */\n:root {\n".implode("\n", $lines)."\n}\n";
    }

    /**
     * @return list<string>
     */
    public function contrastWarnings(): array
    {
        $values = $this->values();
        $warnings = [];

        foreach (TokenCatalog::contrastPairs() as [$foreground, $background, $minimum, $description]) {
            $ratio = Color::fromHex($values[$foreground])->contrastWith(Color::fromHex($values[$background]));
            if ($ratio < $minimum) {
                $warnings[] = sprintf('%s: contrast %.2f:1 is below the required %.1f:1.', $description, $ratio, $minimum);
            }
        }

        return $warnings;
    }

    private function isValid(string $type, string $value): bool
    {
        return match ($type) {
            'color' => (bool) preg_match('/^#[0-9a-f]{6}$/i', $value),
            'font' => array_key_exists($value, TokenCatalog::FONT_STACKS),
            'length' => (bool) preg_match('/^(0|\d{1,3}(\.\d{1,3})?(px|rem))$/', $value),
            default => false,
        };
    }
}
