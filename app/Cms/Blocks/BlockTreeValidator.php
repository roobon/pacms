<?php

namespace App\Cms\Blocks;

use App\Cms\Display\DisplayModeRegistry;
use App\Cms\Fields\FieldValidator;
use App\Cms\Sources\SourceRegistry;
use App\Cms\Validation\Errors;
use App\Cms\Validation\ValueValidator;
use App\Models\User;
use App\Support\Html\HtmlSanitizer;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Validates and cleans a whole block tree (CMS-ARCHITECTURE.md §5, SECURITY-ARCHITECTURE.md §4):
 * known types, parent/child rules, depth and size limits, field values, sources, display,
 * layout/style/responsive/advanced. Error keys are "blocks.<uuid>.<section>.<field>" so the
 * builder can show them on the right block.
 */
class BlockTreeValidator
{
    private Errors $errors;

    private ValueValidator $values;

    private int $count = 0;

    /** @var array<string, true> */
    private array $seenUuids = [];

    private bool $mayUseAttributes = false;

    public function __construct(
        private readonly BlockRegistry $registry,
        private readonly SourceRegistry $sources,
        private readonly DisplayModeRegistry $display,
        private readonly HtmlSanitizer $html,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @return list<array<string, mixed>>
     *
     * @throws ValidationException
     */
    public function validate(array $nodes, ?User $user = null): array
    {
        $this->errors = new Errors;
        $this->values = new ValueValidator($this->errors);
        $this->count = 0;
        $this->seenUuids = [];
        $this->mayUseAttributes = (bool) $user?->can('blocks.custom_attributes');

        if (! array_is_list($nodes)) {
            throw ValidationException::withMessages(['blocks' => __('Invalid block structure.')]);
        }

        $clean = $this->nodes($nodes, null, 1);

        if ($this->count > (int) config('pacms.blocks.max_nodes')) {
            $this->errors->add('blocks', __('A page can contain at most :max blocks.', ['max' => config('pacms.blocks.max_nodes')]));
        }

        $this->errors->throwIfAny();

        return $clean;
    }

    /**
     * @param  array<mixed>  $nodes
     * @return list<array<string, mixed>>
     */
    private function nodes(array $nodes, ?BlockType $parent, int $depth): array
    {
        if ($depth > (int) config('pacms.blocks.max_depth')) {
            $this->errors->add('blocks', __('Blocks can be nested at most :max levels deep.', ['max' => config('pacms.blocks.max_depth')]));

            return [];
        }

        if ($parent !== null && count($nodes) > $parent->maxChildren()) {
            $this->errors->add('blocks', __(':type can contain at most :max blocks.', ['type' => $parent->label(), 'max' => $parent->maxChildren()]));
        }

        $clean = [];
        foreach ($nodes as $node) {
            if (is_array($node) && ($cleanNode = $this->node($node, $parent, $depth)) !== null) {
                $clean[] = $cleanNode;
            }
        }

        return $clean;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>|null
     */
    private function node(array $node, ?BlockType $parent, int $depth): ?array
    {
        $this->count++;
        $uuid = $this->uuid($node['uuid'] ?? null);
        $path = "blocks.{$uuid}";
        $type = $this->registry->find((string) ($node['type'] ?? ''));

        if ($type === null) {
            $this->errors->add($path, __('Unknown block type ":type".', ['type' => (string) ($node['type'] ?? '')]));

            return null;
        }

        if (! $type->allowedUnder($parent)) {
            $this->errors->add($path, $parent === null
                ? __(':type cannot be placed at page level.', ['type' => $type->label()])
                : __(':type cannot be placed inside :parent.', ['type' => $type->label(), 'parent' => $parent->label()]));

            return null;
        }

        $fieldValidator = new FieldValidator($this->values, $this->html);
        $children = (array) ($node['children'] ?? []);

        $clean = array_filter([
            'uuid' => $uuid,
            'type' => $type->slug(),
            'name' => isset($node['name']) && $node['name'] !== '' ? mb_substr(HtmlSanitizer::plain((string) $node['name']), 0, 120) : null,
            'hidden' => ! empty($node['hidden']) ? true : null,
            'content' => $fieldValidator->validate(array_map(fn ($f) => $f->toArray(), $type->fields()), (array) ($node['content'] ?? []), "{$path}.content"),
            'source' => $this->sources->validate($type, (array) ($node['source'] ?? []), $this->values, "{$path}.source"),
            'display' => $this->display->validate((array) ($node['display'] ?? []), $type->displayModes(), $this->values, "{$path}.display"),
            'layout' => (new StyleValidator($this->values))->layout((array) ($node['layout'] ?? []), "{$path}.layout"),
            'style' => (new StyleValidator($this->values))->style((array) ($node['style'] ?? []), "{$path}.style"),
            'responsive' => (new StyleValidator($this->values))->responsive((array) ($node['responsive'] ?? []), "{$path}.responsive"),
            'advanced' => (new StyleValidator($this->values))->advanced((array) ($node['advanced'] ?? []), "{$path}.advanced", $this->mayUseAttributes),
        ], fn ($value) => $value !== null && $value !== []);

        if ($children !== []) {
            if (! $type->acceptsChildren()) {
                $this->errors->add($path, __(':type cannot contain other blocks.', ['type' => $type->label()]));
            } else {
                $clean['children'] = $this->nodes($children, $type, $depth + 1);
            }
        }

        return $clean;
    }

    /**
     * Keep a valid, unique ULID; otherwise assign a new one.
     */
    private function uuid(mixed $uuid): string
    {
        $uuid = is_string($uuid) ? strtolower($uuid) : '';

        if (! preg_match('/^[0-9a-z]{26}$/', $uuid) || isset($this->seenUuids[$uuid])) {
            $uuid = strtolower((string) Str::ulid());
        }

        $this->seenUuids[$uuid] = true;

        return $uuid;
    }
}
