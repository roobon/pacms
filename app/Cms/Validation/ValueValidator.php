<?php

namespace App\Cms\Validation;

use App\Cms\Content\ContentTypeRegistry;
use App\Cms\Design\TokenCatalog;
use App\Enums\MediaKind;
use App\Models\Media;
use App\Models\Page;

/**
 * Validates the typed values used inside blocks: design-token references, lengths,
 * colours, media references and links. Every method returns the cleaned value or null
 * (and records an error). Media and entity existence lookups are memoised per instance.
 */
final class ValueValidator
{
    public const LENGTH_UNITS = ['px', 'rem', 'em', '%', 'vh', 'vw'];

    /**
     * Whether an entity link target exists: a page or an item of any content module.
     */
    public static function linkTargetExists(mixed $entity, mixed $id): bool
    {
        if (! is_string($entity) || ! is_numeric($id)) {
            return false;
        }
        $query = $entity === 'pages' ? Page::query() : app(ContentTypeRegistry::class)->find($entity)?->query();

        return $query !== null && $query->whereKey((int) $id)->exists();
    }

    /** @var array<int, Media|null> */
    private array $media = [];

    /**
     * @param  array<string, true>  $pendingAssets  asset keys of a JSON import that are not downloaded yet
     */
    public function __construct(private readonly Errors $errors, private readonly array $pendingAssets = []) {}

    public function errors(): Errors
    {
        return $this->errors;
    }

    /**
     * {"$token": "space.4"} where the token exists and starts with one of the prefixes.
     *
     * @param  list<string>  $prefixes
     * @return array{'$token': string}|null
     */
    public function token(mixed $value, array $prefixes, string $path): ?array
    {
        $name = is_array($value) ? ($value['$token'] ?? null) : null;

        if (! is_string($name) || ! array_key_exists($name, TokenCatalog::definitions())) {
            $this->errors->add($path, __('Unknown design token.'));

            return null;
        }

        foreach ($prefixes as $prefix) {
            if (str_starts_with($name, $prefix.'.')) {
                return ['$token' => $name];
            }
        }

        $this->errors->add($path, __('This design token cannot be used here.'));

        return null;
    }

    /**
     * {"value": 12, "unit": "px"} within bounds.
     *
     * @param  list<string>  $units
     * @return array{value: int|float, unit: string}|null
     */
    public function length(mixed $value, string $path, array $units = self::LENGTH_UNITS, float $min = 0, float $max = 2000): ?array
    {
        if (! is_array($value) || ! is_numeric($value['value'] ?? null) || ! in_array($value['unit'] ?? null, $units, true)) {
            $this->errors->add($path, __('Enter a number with a unit (:units).', ['units' => implode(', ', $units)]));

            return null;
        }

        $number = $value['value'] + 0;
        if ($number < $min || $number > $max) {
            $this->errors->add($path, __('Must be between :min and :max.', ['min' => $min, 'max' => $max]));

            return null;
        }

        return ['value' => $number, 'unit' => $value['unit']];
    }

    /**
     * A space token or a length.
     *
     * @return array<string, mixed>|null
     */
    public function spacing(mixed $value, string $path, float $min = 0, float $max = 400): ?array
    {
        if (is_array($value) && isset($value['$token'])) {
            return $this->token($value, ['space'], $path);
        }

        return $this->length($value, $path, ['px', 'rem', 'em', '%', 'vh', 'vw'], $min, $max);
    }

    /**
     * A colour token or #RRGGBB / #RRGGBBAA.
     *
     * @return array{'$token': string}|string|null
     */
    public function color(mixed $value, string $path): array|string|null
    {
        if (is_array($value) && isset($value['$token'])) {
            return $this->token($value, ['color'], $path);
        }

        if (is_string($value) && preg_match('/^#([0-9a-f]{6}|[0-9a-f]{8})$/i', $value)) {
            return strtoupper($value);
        }

        $this->errors->add($path, __('Choose a theme colour or enter a hex colour like #0A6B66.'));

        return null;
    }

    /**
     * @param  array<int, string>  $allowed
     */
    public function enum(mixed $value, array $allowed, string $path): ?string
    {
        if (is_string($value) && in_array($value, $allowed, true)) {
            return $value;
        }

        $this->errors->add($path, __('Choose one of: :options.', ['options' => implode(', ', $allowed)]));

        return null;
    }

    /**
     * {"$media": 12} referencing an existing media item (optionally of one kind, public).
     *
     * @return array{'$media': int}|array{'$asset': string}|null
     */
    public function mediaRef(mixed $value, string $path, ?MediaKind $kind = null): ?array
    {
        // JSON import: an asset declared in the document and not downloaded yet.
        if (is_array($value) && is_string($value['$asset'] ?? null) && isset($this->pendingAssets[$value['$asset']])) {
            return ['$asset' => $value['$asset']];
        }

        $id = is_array($value) ? ($value['$media'] ?? null) : null;

        if (! is_int($id) && ! (is_string($id) && ctype_digit($id))) {
            $this->errors->add($path, __('Choose a file from the media library.'));

            return null;
        }

        $id = (int) $id;
        $media = $this->media[$id] ??= Media::query()->find($id);

        if ($media === null) {
            $this->errors->add($path, __('The selected file no longer exists.'));

            return null;
        }

        if ($kind !== null && $media->kind !== $kind) {
            $this->errors->add($path, __('Choose a :kind file.', ['kind' => strtolower($kind->label())]));

            return null;
        }

        if (! $media->isPublic()) {
            $this->errors->add($path, __('Private files cannot be shown on the public website.'));

            return null;
        }

        return ['$media' => $id];
    }

    /**
     * Link object (CMS-BLOCK-SCHEMA.md §8.4). Only safe protocols are accepted.
     *
     * @return array<string, mixed>|null
     */
    public function link(mixed $value, string $path): ?array
    {
        if (! is_array($value) || ! isset($value['type'])) {
            $this->errors->add($path, __('Enter a link.'));

            return null;
        }

        $newTab = (bool) ($value['new_tab'] ?? false);

        switch ($value['type']) {
            case 'url':
                $url = trim((string) ($value['url'] ?? ''));
                if (! self::isSafeUrl($url)) {
                    $this->errors->add($path.'.url', __('Enter a web address starting with https://, http://, / or #.'));

                    return null;
                }

                return ['type' => 'url', 'url' => mb_substr($url, 0, 2048), 'new_tab' => $newTab];

            case 'entity':
                $entity = $value['entity'] ?? null;
                $id = $value['id'] ?? null;
                if (! self::linkTargetExists($entity, $id)) {
                    $this->errors->add($path, __('The linked page no longer exists.'));

                    return null;
                }

                return ['type' => 'entity', 'entity' => $entity, 'id' => (int) $id, 'new_tab' => $newTab];

            case 'anchor':
                $anchor = (string) ($value['anchor'] ?? '');
                if (! preg_match('/^[a-z][a-z0-9-]{0,63}$/', $anchor)) {
                    $this->errors->add($path.'.anchor', __('Use lowercase letters, numbers and hyphens.'));

                    return null;
                }

                return ['type' => 'anchor', 'anchor' => $anchor];

            case 'email':
                $email = (string) ($value['email'] ?? '');
                if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $this->errors->add($path.'.email', __('Enter a valid e-mail address.'));

                    return null;
                }

                return ['type' => 'email', 'email' => $email];
        }

        $this->errors->add($path, __('Unsupported link type.'));

        return null;
    }

    /**
     * http(s), root-relative, #anchor, mailto: or tel: — never javascript:, data:, vbscript: …
     */
    public static function isSafeUrl(string $url): bool
    {
        if ($url === '' || preg_match('/[\x00-\x1F\x7F]/', $url)) {
            return false;
        }

        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return true;
        }

        if (str_starts_with($url, '#')) {
            return true;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return match ($scheme) {
            'http', 'https' => (bool) filter_var($url, FILTER_VALIDATE_URL),
            'mailto', 'tel' => true,
            default => false,
        };
    }
}
