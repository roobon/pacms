<?php

namespace App\Cms\Fields;

use App\Cms\Validation\Errors;
use App\Support\Html\HtmlSanitizer;
use Illuminate\Validation\ValidationException;

/**
 * Validates field definitions built in the admin's field builder (custom block types,
 * CMS-ARCHITECTURE.md §11.2) and returns the clean definitions that drive the inspector
 * form, server validation and rendering. Only whitelisted keys survive.
 */
final class FieldDefinitionValidator
{
    public const TYPES = [
        'text' => 'Text', 'textarea' => 'Long text', 'rich-text' => 'Rich text', 'number' => 'Number',
        'checkbox' => 'Checkbox', 'select' => 'Dropdown', 'radio' => 'Radio buttons', 'multi-select' => 'Multiple choice',
        'link' => 'Link', 'url' => 'Web address', 'email' => 'E-mail', 'image' => 'Image', 'media' => 'File',
        'icon' => 'Icon', 'color' => 'Colour', 'date' => 'Date', 'time' => 'Time', 'datetime' => 'Date and time',
        'video-url' => 'Video link', 'repeater' => 'Repeater (list of rows)',
    ];

    public const MAX_FIELDS = 40;

    /** Repeaters inside repeaters, at most (CMS-ARCHITECTURE.md §5.3). */
    public const MAX_REPEATER_DEPTH = 3;

    private Errors $errors;

    /**
     * @param  mixed  $definitions  raw list from the field builder
     * @return list<array<string, mixed>>
     *
     * @throws ValidationException
     */
    public function validate(mixed $definitions): array
    {
        $this->errors = new Errors;
        $clean = $this->list($definitions, 'fields', 1);
        $this->errors->throwIfAny();

        return $clean;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function list(mixed $definitions, string $path, int $depth): array
    {
        if (! is_array($definitions) || ! array_is_list($definitions)) {
            $this->errors->add($path, __('Invalid field list.'));

            return [];
        }

        if (count($definitions) > self::MAX_FIELDS) {
            $this->errors->add($path, __('Use at most :max fields.', ['max' => self::MAX_FIELDS]));

            return [];
        }

        $clean = [];
        $keys = [];
        foreach ($definitions as $index => $definition) {
            $field = $this->field(is_array($definition) ? $definition : [], "{$path}.{$index}", $depth);
            if ($field === null) {
                continue;
            }
            if (isset($keys[$field['key']])) {
                $this->errors->add("{$path}.{$index}.key", __('The key ":key" is used twice.', ['key' => $field['key']]));

                continue;
            }
            $keys[$field['key']] = true;
            $clean[] = $field;
        }

        return $clean;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>|null
     */
    private function field(array $input, string $path, int $depth): ?array
    {
        $key = is_string($input['key'] ?? null) ? $input['key'] : '';
        $type = is_string($input['type'] ?? null) ? $input['type'] : '';
        $label = HtmlSanitizer::plain(is_scalar($input['label'] ?? null) ? (string) $input['label'] : '');

        $valid = true;
        if (! preg_match('/^[a-z][a-z0-9_]{0,39}$/', $key) || $key === 'item') {
            $this->errors->add("{$path}.key", __('Keys use lowercase letters, numbers and _, start with a letter, and cannot be "item".'));
            $valid = false;
        }
        if (! isset(self::TYPES[$type])) {
            $this->errors->add("{$path}.type", __('Choose a field type.'));
            $valid = false;
        }
        if ($label === '' || mb_strlen($label) > 80) {
            $this->errors->add("{$path}.label", __('Enter a label of up to 80 characters.'));
            $valid = false;
        }
        if (! $valid) {
            return null;
        }

        $field = ['key' => $key, 'type' => $type, 'label' => $label];

        if (! empty($input['required'])) {
            $field['required'] = true;
        }
        if (isset($input['help']) && is_scalar($input['help']) && trim((string) $input['help']) !== '') {
            $field['help'] = mb_substr(HtmlSanitizer::plain((string) $input['help']), 0, 200);
        }

        if (in_array($type, ['text', 'textarea', 'rich-text', 'url', 'email', 'video-url'], true)) {
            $ceiling = ['text' => 255, 'textarea' => 5000, 'rich-text' => 100000, 'url' => 2048, 'email' => 191, 'video-url' => 2048][$type];
            $field['max'] = $this->integer($input['max'] ?? null, 1, $ceiling, $ceiling, "{$path}.max");
        }

        if ($type === 'number') {
            foreach (['min', 'max'] as $bound) {
                if (isset($input[$bound]) && $input[$bound] !== '') {
                    is_numeric($input[$bound])
                        ? $field[$bound] = $input[$bound] + 0
                        : $this->errors->add("{$path}.{$bound}", __('Enter a number.'));
                }
            }
        }

        if (in_array($type, ['select', 'radio', 'multi-select'], true)) {
            $field['options'] = $this->options($input['options'] ?? null, "{$path}.options");
        }

        if ($type === 'checkbox') {
            $field['default'] = ! empty($input['default']);
        }

        if ($type === 'repeater') {
            if ($depth >= self::MAX_REPEATER_DEPTH) {
                $this->errors->add("{$path}.type", __('Repeaters can be nested at most :max levels deep.', ['max' => self::MAX_REPEATER_DEPTH]));

                return null;
            }
            $field['fields'] = $this->list($input['fields'] ?? [], "{$path}.fields", $depth + 1);
            if ($field['fields'] === []) {
                $this->errors->add("{$path}.fields", __('Add at least one field to each row.'));
            }
            $field['min_items'] = $this->integer($input['min_items'] ?? null, 0, 50, 0, "{$path}.min_items");
            $field['max_items'] = $this->integer($input['max_items'] ?? null, max(1, $field['min_items']), 50, 50, "{$path}.max_items");
        }

        return $field;
    }

    /**
     * @return array<string, string>
     */
    private function options(mixed $options, string $path): array
    {
        $clean = [];

        foreach (is_array($options) ? $options : [] as $value => $label) {
            // Accept both {"value": "Label"} and [{"value": …, "label": …}].
            if (is_array($label)) {
                [$value, $label] = [$label['value'] ?? '', $label['label'] ?? ''];
            }
            $value = is_scalar($value) ? trim((string) $value) : '';
            $label = is_scalar($label) ? HtmlSanitizer::plain((string) $label) : '';

            if (! preg_match('/^[A-Za-z0-9_-]{1,40}$/', $value) || $label === '') {
                $this->errors->add($path, __('Each option needs a value (letters, numbers, - and _) and a label.'));

                return [];
            }
            $clean[$value] = mb_substr($label, 0, 80);
        }

        if (count($clean) < 1 || count($clean) > 50) {
            $this->errors->add($path, __('Add between 1 and 50 options.'));
        }

        return $clean;
    }

    private function integer(mixed $value, int $min, int $max, int $default, string $path): int
    {
        if ($value === null || $value === '') {
            return $default;
        }

        if (! is_numeric($value) || (int) $value < $min || (int) $value > $max) {
            $this->errors->add($path, __('Enter a whole number between :min and :max.', ['min' => $min, 'max' => $max]));

            return $default;
        }

        return (int) $value;
    }
}
