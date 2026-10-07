<?php

namespace App\Cms\Fields;

/**
 * A field definition (CMS-ARCHITECTURE.md §11.2). One definition drives the builder's
 * inspector form, server-side validation and (Phase 6) the generated JSON Schema.
 *
 * Supported types: text, textarea, rich-text, number, checkbox, select, radio, multi-select,
 * link, url, email, image, media, icon, color, date, time, datetime, video-url, repeater.
 */
final class Field
{
    /** @var array<string, mixed> */
    private array $definition;

    private function __construct(string $type, string $key)
    {
        $this->definition = ['key' => $key, 'type' => $type, 'label' => ucfirst(str_replace('_', ' ', $key))];
    }

    public static function text(string $key): self
    {
        return (new self('text', $key))->max(255);
    }

    public static function textarea(string $key): self
    {
        return (new self('textarea', $key))->max(5000);
    }

    public static function richText(string $key): self
    {
        return (new self('rich-text', $key))->max(100000);
    }

    public static function number(string $key): self
    {
        return new self('number', $key);
    }

    public static function checkbox(string $key): self
    {
        return (new self('checkbox', $key))->default(false);
    }

    /**
     * @param  array<int|string, string>  $options  value => label
     */
    public static function select(string $key, array $options): self
    {
        $field = new self('select', $key);
        $field->definition['options'] = $options;

        return $field;
    }

    /**
     * @param  array<int|string, string>  $options  value => label
     */
    public static function radio(string $key, array $options): self
    {
        $field = new self('radio', $key);
        $field->definition['options'] = $options;

        return $field;
    }

    /**
     * @param  array<int|string, string>  $options  value => label
     */
    public static function multiSelect(string $key, array $options): self
    {
        $field = new self('multi-select', $key);
        $field->definition['options'] = $options;

        return $field;
    }

    public static function url(string $key): self
    {
        return (new self('url', $key))->max(2048);
    }

    public static function time(string $key): self
    {
        return new self('time', $key);
    }

    public static function datetime(string $key): self
    {
        return new self('datetime', $key);
    }

    /**
     * A definition that was already validated (FieldDefinitionValidator), e.g. a custom
     * block type's fields stored in the database.
     *
     * @param  array<string, mixed>  $definition
     */
    public static function fromArray(array $definition): self
    {
        $field = new self((string) $definition['type'], (string) $definition['key']);
        $field->definition = $definition;

        return $field;
    }

    public static function link(string $key): self
    {
        return new self('link', $key);
    }

    public static function email(string $key): self
    {
        return (new self('email', $key))->max(191);
    }

    public static function image(string $key): self
    {
        return new self('image', $key);
    }

    public static function media(string $key): self
    {
        return new self('media', $key);
    }

    public static function icon(string $key): self
    {
        return new self('icon', $key);
    }

    public static function color(string $key): self
    {
        return new self('color', $key);
    }

    public static function date(string $key): self
    {
        return new self('date', $key);
    }

    public static function videoUrl(string $key): self
    {
        return (new self('video-url', $key))->max(2048);
    }

    /**
     * @param  list<Field>  $fields
     */
    public static function repeater(string $key, array $fields): self
    {
        $field = new self('repeater', $key);
        $field->definition['fields'] = array_map(fn (Field $f) => $f->toArray(), $fields);
        $field->definition['min_items'] = 0;
        $field->definition['max_items'] = 50;

        return $field;
    }

    public function label(string $label): self
    {
        $this->definition['label'] = $label;

        return $this;
    }

    public function help(string $help): self
    {
        $this->definition['help'] = $help;

        return $this;
    }

    public function required(bool $required = true): self
    {
        $this->definition['required'] = $required;

        return $this;
    }

    public function default(mixed $value): self
    {
        $this->definition['default'] = $value;

        return $this;
    }

    public function min(int|float $min): self
    {
        $this->definition['min'] = $min;

        return $this;
    }

    public function max(int|float $max): self
    {
        $this->definition['max'] = $max;

        return $this;
    }

    public function items(int $min, int $max): self
    {
        $this->definition['min_items'] = $min;
        $this->definition['max_items'] = $max;

        return $this;
    }

    public function placeholder(string $placeholder): self
    {
        $this->definition['placeholder'] = $placeholder;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->definition;
    }
}
