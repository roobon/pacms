<?php

namespace App\Cms\Fields;

/**
 * Field bindings inside a custom block type's structure (CMS-ARCHITECTURE.md §11.1):
 * a block value {"$bind": "photo"} takes the instance's "photo" value, {"$bind": "item.url"}
 * the current row of the enclosing `repeat` block. Bindings are references, not
 * expressions: there is nothing to evaluate.
 */
final class Bindings
{
    /**
     * Which source field types may fill which target field types.
     */
    private const COMPATIBLE = [
        'text' => ['text', 'textarea', 'number', 'url', 'email', 'date', 'time', 'datetime', 'select', 'radio'],
        'textarea' => ['text', 'textarea'],
        'rich-text' => ['rich-text', 'text', 'textarea'],
        'number' => ['number'],
        'image' => ['image'],
        'media' => ['image', 'media'],
        'link' => ['link', 'url'],
        'url' => ['url'],
        'video-url' => ['video-url'],
        'icon' => ['icon'],
        'color' => ['color'],
        'date' => ['date'],
        'time' => ['time'],
        'datetime' => ['datetime'],
        'checkbox' => ['checkbox'],
        'email' => ['email'],
    ];

    /**
     * @param  array<string, array<string, mixed>>  $fields  the custom type's fields by key
     * @param  list<array<string, array<string, mixed>>>  $items  sub-fields of enclosing repeats, innermost last
     */
    private function __construct(private readonly array $fields, private readonly array $items = []) {}

    /**
     * @param  list<array<string, mixed>>  $fields
     */
    public static function for(array $fields): self
    {
        return new self(array_column($fields, null, 'key'));
    }

    public static function isBinding(mixed $value): bool
    {
        return is_array($value) && array_keys($value) === ['$bind'] && is_string($value['$bind']);
    }

    /**
     * The field a binding path points to: "photo" or "item.url", or null.
     *
     * @return array<string, mixed>|null
     */
    public function resolve(string $path): ?array
    {
        if (str_starts_with($path, 'item.')) {
            $scope = $this->items[array_key_last($this->items) ?? -1] ?? null;

            return $scope[substr($path, 5)] ?? null;
        }

        return $this->fields[$path] ?? null;
    }

    /**
     * Error message, or null when $path may fill a field of type $targetType.
     */
    public function check(string $path, string $targetType): ?string
    {
        $source = $this->resolve($path);

        if ($source === null) {
            return __('Linked to a field that does not exist: :path.', ['path' => $path]);
        }

        if (! in_array($source['type'], self::COMPATIBLE[$targetType] ?? [], true)) {
            return __('The field ":label" cannot fill this setting.', ['label' => $source['label'] ?? $path]);
        }

        return null;
    }

    /**
     * Enter a `repeat` block over the repeater at $path ("links" or "item.links").
     */
    public function enterRepeat(string $path): ?self
    {
        $field = $this->resolve($path);

        if (($field['type'] ?? null) !== 'repeater') {
            return null;
        }

        return new self($this->fields, [...$this->items, array_column($field['fields'], null, 'key')]);
    }

    /**
     * Field types that can fill a target type (for the builder's binding menu).
     *
     * @return array<string, list<string>>
     */
    public static function compatibility(): array
    {
        return self::COMPATIBLE;
    }
}
