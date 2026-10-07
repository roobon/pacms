<?php

namespace App\Cms\Blocks;

use App\Cms\Fields\Bindings;
use App\Models\GlobalBlock;
use App\Models\Revision;
use App\Support\Html\HtmlSanitizer;

/**
 * Replaces reusable blocks with ordinary blocks before a tree is resolved for rendering
 * (CMS-ARCHITECTURE.md §11.3, §12):
 *
 * - `global-ref` → the global block's published tree;
 * - custom block instances → the type's published structure with bindings filled from the
 *   instance's values, `repeat` blocks unrolled per row and `when` blocks evaluated.
 *
 * The result contains only core block types, so the normal resolver and the same React
 * components render it, and no binding or condition ever reaches the browser. Expanded
 * blocks are marked `locked`: in the builder preview a click selects their owner.
 */
class BlockExpander
{
    /** @var array<int, list<array<string, mixed>>> global block id => published tree */
    private array $globals = [];

    public function __construct(
        private readonly BlockRegistry $registry,
        private readonly HtmlSanitizer $html,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @return list<array<string, mixed>>
     */
    public function expand(array $nodes): array
    {
        $this->globals = $this->loadGlobals($nodes);

        return $this->walk($nodes, false);
    }

    /**
     * Preview of a custom type's (unsaved) structure filled with sample values, for its
     * editor. The first copy of each structure block keeps its uuid so it stays selectable;
     * further repeat rows are locked copies.
     *
     * @param  list<array<string, mixed>>  $structure  validated (structure context)
     * @param  list<array<string, mixed>>  $fields  validated field definitions
     * @return list<array<string, mixed>>
     */
    public function preview(array $structure, array $fields): array
    {
        $this->globals = [];
        $scope = new Scope(array_column($fields, null, 'key'), $this->sampleValues($fields));

        return $this->bind($structure, $scope, 'preview', '', keepUuids: true);
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @return list<array<string, mixed>>
     */
    private function walk(array $nodes, bool $locked): array
    {
        $out = [];

        foreach ($nodes as $node) {
            $type = $this->registry->find((string) ($node['type'] ?? ''));

            if ($locked) {
                $node['locked'] = true;
            }

            if ($node['type'] === 'global-ref') {
                $node['children'] = $this->walk($this->globals[(int) ($node['global_block_id'] ?? 0)] ?? [], true);
            } elseif ($type instanceof CustomBlockType) {
                $values = (array) ($node['content'] ?? []);
                $scope = new Scope(array_column(array_map(fn ($f) => $f->toArray(), $type->fields()), null, 'key'), $values);
                $node['children'] = $this->walk($this->bind($type->structure, $scope, (string) $node['uuid'], ''), true);
                $node['content'] = [];
            } elseif (! empty($node['children'])) {
                $node['children'] = $this->walk($node['children'], $locked);
            }

            $out[] = $node;
        }

        return $out;
    }

    /**
     * Instantiate a custom type's structure for one instance.
     *
     * @param  list<array<string, mixed>>  $structure
     * @return list<array<string, mixed>>
     */
    private function bind(array $structure, Scope $scope, string $instance, string $iteration, bool $keepUuids = false): array
    {
        $out = [];

        foreach ($structure as $node) {
            if (! empty($node['hidden'])) {
                continue;
            }

            $content = (array) ($node['content'] ?? []);

            if ($node['type'] === 'repeat') {
                $rows = $scope->value((string) ($content['field'] ?? ''));
                $rowFields = $scope->field((string) ($content['field'] ?? ''))['fields'] ?? [];
                foreach (is_array($rows) && array_is_list($rows) ? $rows : [] as $index => $row) {
                    $rowScope = $scope->enter(array_column($rowFields, null, 'key'), (array) $row);
                    array_push($out, ...$this->bind($node['children'] ?? [], $rowScope, $instance, "{$iteration}.{$index}", $keepUuids && $index === 0));
                }

                continue;
            }

            if ($node['type'] === 'when') {
                if ($this->condition($scope->value((string) ($content['field'] ?? '')), (string) ($content['operator'] ?? 'filled'), $content['value'] ?? null)) {
                    array_push($out, ...$this->bind($node['children'] ?? [], $scope, $instance, $iteration, $keepUuids));
                }

                continue;
            }

            if (! $keepUuids) {
                $node['uuid'] = $this->derive($instance, (string) $node['uuid'], $iteration);
                if ($instance === 'preview') {
                    $node['locked'] = true;
                }
            }
            $node['content'] = $this->substitute((string) $node['type'], $content, $scope);
            // A block whose required setting is linked to an empty field is left out
            // (e.g. no biography → no empty text block).
            if ($this->missingRequired((string) $node['type'], $node['content'])) {
                continue;
            }
            if (! empty($node['children'])) {
                $node['children'] = $this->bind($node['children'], $scope, $instance, $iteration, $keepUuids);
            }
            $out[] = $node;
        }

        return $out;
    }

    /**
     * Fill {"$bind": path} values from the instance, converted to the target field type.
     *
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    private function substitute(string $typeSlug, array $content, Scope $scope): array
    {
        $targets = [];
        foreach ($this->registry->find($typeSlug)?->fields() ?? [] as $field) {
            $targets[$field->toArray()['key']] = $field->toArray()['type'];
        }

        foreach ($content as $key => $value) {
            if (! Bindings::isBinding($value)) {
                continue;
            }

            $path = $value['$bind'];
            $converted = $this->convert($scope->value($path), (array) $scope->field($path), $targets[$key] ?? '');

            if ($converted === null) {
                unset($content[$key]);
            } else {
                $content[$key] = $converted;
            }
        }

        return $content;
    }

    /**
     * @param  array<string, mixed>  $content
     */
    private function missingRequired(string $typeSlug, array $content): bool
    {
        foreach ($this->registry->find($typeSlug)?->fields() ?? [] as $field) {
            $definition = $field->toArray();
            if (! empty($definition['required']) && ! array_key_exists($definition['key'], $content)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Convert a stored instance value from its field type to the target field type. Values
     * that no longer fit (e.g. after the type's fields changed) are dropped, never guessed.
     *
     * @param  array<string, mixed>  $source  the source field definition
     */
    private function convert(mixed $value, array $source, string $to): mixed
    {
        if ($value === null || $value === '' || $value === []) {
            return null;
        }

        $from = (string) ($source['type'] ?? '');
        $isMedia = is_array($value) && isset($value['$media']) && is_numeric($value['$media']);

        // Choices show their label, not the stored value.
        if (in_array($from, ['select', 'radio'], true) && is_scalar($value)) {
            $value = (string) ($source['options'][(string) $value] ?? $value);
        }

        return match (true) {
            in_array($from, ['image', 'media'], true) => $isMedia ? ['$media' => (int) $value['$media']] : null,
            $from === 'link' => is_array($value) && isset($value['type']) ? $value : null,
            $from === 'url' && $to === 'link' => is_string($value) ? ['type' => 'url', 'url' => $value, 'new_tab' => false] : null,
            $from === 'rich-text' => is_string($value) ? $this->html->sanitize($value) : null,
            $to === 'rich-text' => is_scalar($value) ? '<p>'.nl2br(e((string) $value), false).'</p>' : null,
            $from === 'checkbox' => (bool) $value,
            $from === 'number' => is_numeric($value) ? $value + 0 : null,
            default => is_scalar($value) ? (string) $value : null,
        };
    }

    /**
     * Placeholder values that show where each field appears.
     *
     * @param  list<array<string, mixed>>  $fields
     * @return array<string, mixed>
     */
    private function sampleValues(array $fields, int $depth = 0): array
    {
        $values = [];

        foreach ($fields as $field) {
            $label = '['.($field['label'] ?? $field['key']).']';
            $first = array_key_first($field['options'] ?? []);

            $values[$field['key']] = match ($field['type']) {
                'text', 'textarea' => $label,
                'rich-text' => '<p>'.e($label).'</p>',
                'number' => 123,
                'checkbox' => true,
                'select', 'radio' => $first,
                'multi-select' => $first === null ? null : [$first],
                'link' => ['type' => 'url', 'url' => '#', 'new_tab' => false],
                'url' => '#',
                'email' => 'name@example.org',
                'icon' => 'bi-star',
                'date' => now()->toDateString(),
                'time' => '09:00',
                'datetime' => now()->format('Y-m-d\TH:i'),
                'repeater' => $depth < 2 ? [$this->sampleValues($field['fields'] ?? [], $depth + 1), $this->sampleValues($field['fields'] ?? [], $depth + 1)] : [],
                default => null,
            };
        }

        return $values;
    }

    private function condition(mixed $value, string $operator, mixed $expected): bool
    {
        $filled = ! ($value === null || $value === '' || $value === [] || $value === false);

        return match ($operator) {
            'empty' => ! $filled,
            'equals' => is_scalar($value) && (string) $value === (string) $expected,
            default => $filled,
        };
    }

    /**
     * Stable, unique uuid for a block expanded from a structure (scoped CSS and React keys).
     */
    private function derive(string $instance, string $uuid, string $iteration): string
    {
        return substr(hash('sha256', "{$instance}|{$uuid}|{$iteration}"), 0, 26);
    }

    /**
     * Published trees of every global block the tree references (one query each).
     *
     * @param  list<array<string, mixed>>  $nodes
     * @return array<int, list<array<string, mixed>>>
     */
    private function loadGlobals(array $nodes): array
    {
        $ids = [];
        $visit = function (array $nodes) use (&$visit, &$ids) {
            foreach ($nodes as $node) {
                if (! empty($node['global_block_id'])) {
                    $ids[] = (int) $node['global_block_id'];
                }
                $visit($node['children'] ?? []);
            }
        };
        $visit($nodes);

        if ($ids === []) {
            return [];
        }

        $globals = GlobalBlock::query()->whereKey(array_unique($ids))->whereNotNull('published_revision_id')->get();
        $revisions = Revision::query()->whereKey($globals->pluck('published_revision_id'))->get()->keyBy('id');

        $trees = [];
        foreach ($globals as $global) {
            $trees[$global->id] = array_values((array) ($revisions[$global->published_revision_id]->snapshot['blocks'] ?? []));
        }

        return $trees;
    }
}
