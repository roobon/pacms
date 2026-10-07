<?php

namespace App\Cms\Fields;

use App\Cms\Validation\ValueValidator;
use App\Enums\MediaKind;
use App\Support\Html\HtmlSanitizer;

/**
 * Validates and cleans values against field definitions (arrays from Field::toArray()).
 * Unknown keys are dropped, text is normalised, rich text is sanitised.
 */
final class FieldValidator
{
    /**
     * @param  Bindings|null  $bindings  set when validating a custom block type's structure:
     *                                   values may then be {"$bind": path} references
     */
    public function __construct(
        private readonly ValueValidator $values,
        private readonly HtmlSanitizer $html,
        private readonly ?Bindings $bindings = null,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $fields
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function validate(array $fields, array $input, string $path): array
    {
        $clean = [];

        foreach ($fields as $field) {
            $key = $field['key'];
            $fieldPath = "{$path}.{$key}";
            $value = $input[$key] ?? null;

            if (Bindings::isBinding($value)) {
                $error = $this->bindings === null
                    ? __('Linked values are only allowed in custom block structures.')
                    : $this->bindings->check($value['$bind'], (string) $field['type']);
                if ($error !== null) {
                    $this->values->errors()->add($fieldPath, $error);
                } else {
                    $clean[$key] = ['$bind' => $value['$bind']];
                }

                continue;
            }

            if ($this->isEmpty($value)) {
                if (! empty($field['required'])) {
                    $this->values->errors()->add($fieldPath, __(':label is required.', ['label' => $field['label']]));
                }

                continue;
            }

            $cleaned = $this->value($field, $value, $fieldPath);
            if ($cleaned !== null) {
                $clean[$key] = $cleaned;
            }
        }

        return $clean;
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function value(array $field, mixed $value, string $path): mixed
    {
        $errors = $this->values->errors();

        switch ($field['type']) {
            case 'text':
            case 'textarea':
                if (! is_scalar($value)) {
                    $errors->add($path, __('Enter text.'));

                    return null;
                }
                $text = HtmlSanitizer::plain((string) $value);

                return $this->maxLength($text, $field, $path);

            case 'rich-text':
                if (! is_string($value)) {
                    $errors->add($path, __('Enter text.'));

                    return null;
                }

                return $this->maxLength($this->html->sanitize($value), $field, $path);

            case 'number':
                if (! is_numeric($value)) {
                    $errors->add($path, __('Enter a number.'));

                    return null;
                }
                $number = $value + 0;
                if ((isset($field['min']) && $number < $field['min']) || (isset($field['max']) && $number > $field['max'])) {
                    $errors->add($path, __('Must be between :min and :max.', ['min' => $field['min'] ?? '…', 'max' => $field['max'] ?? '…']));

                    return null;
                }

                return $number;

            case 'checkbox':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);

            case 'select':
            case 'radio':
                return $this->values->enum(is_scalar($value) ? (string) $value : null, array_map('strval', array_keys($field['options'])), $path);

            case 'multi-select':
                $allowed = array_map('strval', array_keys($field['options']));
                if (! is_array($value) || ! array_is_list($value) || array_diff(array_map(fn ($v) => is_scalar($v) ? (string) $v : '', $value), $allowed) !== []) {
                    $errors->add($path, __('Choose from the listed options.'));

                    return null;
                }

                return array_values(array_unique(array_map('strval', $value)));

            case 'link':
                return $this->values->link($value, $path);

            case 'url':
                if (! is_string($value) || ! ValueValidator::isSafeUrl(trim($value))) {
                    $errors->add($path, __('Enter a web address starting with https://, http://, / or #.'));

                    return null;
                }

                return $this->maxLength(trim($value), $field, $path);

            case 'time':
                if (! is_string($value) || ! preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value)) {
                    $errors->add($path, __('Enter a time (HH:MM).'));

                    return null;
                }

                return $value;

            case 'datetime':
                if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}T([01]\d|2[0-3]):[0-5]\d$/', $value) || ! strtotime($value)) {
                    $errors->add($path, __('Enter a date and time.'));

                    return null;
                }

                return $value;

            case 'email':
                if (! is_string($value) || ! filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors->add($path, __('Enter a valid e-mail address.'));

                    return null;
                }

                return $value;

            case 'image':
                return $this->values->mediaRef($value, $path, MediaKind::Image);

            case 'media':
                return $this->values->mediaRef($value, $path);

            case 'icon':
                if (! is_string($value) || ! preg_match('/^bi-[a-z0-9-]{1,60}$/', $value)) {
                    $errors->add($path, __('Choose a Bootstrap Icons name such as bi-tree.'));

                    return null;
                }

                return $value;

            case 'color':
                return $this->values->color($value, $path);

            case 'date':
                if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) || ! strtotime($value)) {
                    $errors->add($path, __('Enter a date (YYYY-MM-DD).'));

                    return null;
                }

                return $value;

            case 'video-url':
                if (! is_string($value) || VideoUrl::parse($value) === null) {
                    $errors->add($path, __('Enter a YouTube or Vimeo link.'));

                    return null;
                }

                return $value;

            case 'repeater':
                return $this->repeater($field, $value, $path);
        }

        $errors->add($path, __('Unsupported field type.'));

        return null;
    }

    /**
     * @param  array<string, mixed>  $field
     * @return list<array<string, mixed>>|null
     */
    private function repeater(array $field, mixed $value, string $path): ?array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            $this->values->errors()->add($path, __('Invalid list.'));

            return null;
        }

        if (count($value) < ($field['min_items'] ?? 0) || count($value) > ($field['max_items'] ?? 50)) {
            $this->values->errors()->add($path, __('Add between :min and :max items.', ['min' => $field['min_items'] ?? 0, 'max' => $field['max_items'] ?? 50]));

            return null;
        }

        $rows = [];
        foreach ($value as $index => $row) {
            $rows[] = $this->validate($field['fields'], is_array($row) ? $row : [], "{$path}.{$index}");
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function maxLength(string $value, array $field, string $path): ?string
    {
        if (isset($field['max']) && mb_strlen($value) > $field['max']) {
            $this->values->errors()->add($path, __('Use at most :max characters.', ['max' => $field['max']]));

            return null;
        }

        return $value;
    }

    private function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [] || (is_string($value) && trim($value) === '');
    }
}
