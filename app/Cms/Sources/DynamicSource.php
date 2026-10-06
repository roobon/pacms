<?php

namespace App\Cms\Sources;

use App\Cms\Validation\ValueValidator;
use App\Models\Term;

/**
 * A whitelisted query over one content type for dynamic blocks (CMS-ARCHITECTURE.md §6.3).
 * Blocks can only choose from the filters and orders declared here, so no column name,
 * operator or SQL fragment from input ever reaches the query builder.
 */
abstract class DynamicSource
{
    public const MAX_LIMIT = 24;

    abstract public function entity(): string;

    /**
     * filter key => definition ['type' => 'bool'|'term', 'taxonomy' => …, 'label' => …]
     *
     * @return array<string, array<string, string>>
     */
    abstract public function filters(): array;

    /**
     * order key => label
     *
     * @return array<string, string>
     */
    abstract public function orders(): array;

    /**
     * Normalised items (CMS-ARCHITECTURE.md §6.4) for a validated source config.
     *
     * @param  array<string, mixed>  $config
     * @return list<array<string, mixed>>
     */
    abstract public function items(array $config): array;

    /**
     * @param  array<string, mixed>  $source
     * @return array<string, mixed>
     */
    public function validate(array $source, ValueValidator $values, string $path): array
    {
        $clean = [
            'mode' => 'dynamic',
            'provider' => 'cms',
            'entity' => $this->entity(),
            'order' => $values->enum($source['order'] ?? array_key_first($this->orders()), array_keys($this->orders()), "{$path}.order"),
            'limit' => max(1, min(self::MAX_LIMIT, (int) ($source['limit'] ?? 6))),
        ];

        $filters = [];
        foreach ((array) ($source['filters'] ?? []) as $key => $value) {
            $definition = $this->filters()[$key] ?? null;
            if ($definition === null || $value === null || $value === '' || $value === false) {
                continue;
            }

            if ($definition['type'] === 'bool') {
                $filters[$key] = true;
            } elseif ($definition['type'] === 'term') {
                $exists = is_numeric($value) && Term::query()
                    ->where('taxonomy', $definition['taxonomy'])
                    ->whereKey((int) $value)
                    ->exists();
                if ($exists) {
                    $filters[$key] = (int) $value;
                } else {
                    $values->errors()->add("{$path}.filters.{$key}", __('This category no longer exists.'));
                }
            }
        }

        if ($filters !== []) {
            $clean['filters'] = $filters;
        }

        return $clean;
    }

    /**
     * Definition for the builder (filters with options, orders).
     *
     * @return array<string, mixed>
     */
    public function describe(): array
    {
        $filters = [];
        foreach ($this->filters() as $key => $definition) {
            $filters[$key] = $definition + (
                $definition['type'] === 'term'
                    ? ['options' => Term::query()->where('taxonomy', $definition['taxonomy'])->orderBy('name')->pluck('name', 'id')->all()]
                    : []
            );
        }

        return ['entity' => $this->entity(), 'filters' => $filters, 'orders' => $this->orders(), 'max_limit' => self::MAX_LIMIT];
    }
}
