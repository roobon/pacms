<?php

namespace App\Cms\Blocks;

/**
 * Values available to bindings while a custom block structure is expanded: the instance's
 * fields, plus the current row of each enclosing `repeat` ("item.…", innermost row).
 */
final class Scope
{
    /**
     * @param  array<string, array<string, mixed>>  $fields  field definitions by key
     * @param  array<string, mixed>  $values
     */
    public function __construct(
        private readonly array $fields,
        private readonly array $values,
        private readonly ?Scope $item = null,
    ) {}

    /**
     * @param  array<string, array<string, mixed>>  $rowFields
     * @param  array<string, mixed>  $row
     */
    public function enter(array $rowFields, array $row): self
    {
        return new self($this->fields, $this->values, new self($rowFields, $row));
    }

    public function value(string $path): mixed
    {
        if (str_starts_with($path, 'item.')) {
            return $this->item?->values[substr($path, 5)] ?? null;
        }

        return $this->values[$path] ?? null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function field(string $path): ?array
    {
        if (str_starts_with($path, 'item.')) {
            return $this->item?->fields[substr($path, 5)] ?? null;
        }

        return $this->fields[$path] ?? null;
    }
}
