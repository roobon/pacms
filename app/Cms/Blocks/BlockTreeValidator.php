<?php

namespace App\Cms\Blocks;

use App\Cms\Blocks\Types\WhenBlock;
use App\Cms\Display\DisplayModeRegistry;
use App\Cms\Fields\Bindings;
use App\Cms\Fields\FieldValidator;
use App\Cms\Sources\SourceRegistry;
use App\Cms\Validation\Errors;
use App\Cms\Validation\ValueValidator;
use App\Models\GlobalBlock;
use App\Models\User;
use App\Support\Html\HtmlSanitizer;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Validates and cleans a whole block tree (CMS-ARCHITECTURE.md §5, SECURITY-ARCHITECTURE.md §4):
 * known types, where each type may be used, parent/child rules, depth and size limits,
 * field values, sources, display, layout/style/responsive/advanced, global block
 * references and (in custom block structures) field bindings. Error keys are
 * "blocks.<uuid>.<section>.<field>" so the builder can show them on the right block.
 */
class BlockTreeValidator
{
    /** Pages and other content. */
    public const CONTEXT_PAGE = 'page';

    public const CONTEXT_GLOBAL = 'global';

    public const CONTEXT_TEMPLATE = 'template';

    /** A custom block type's structure: bindings, repeat and when are allowed. */
    public const CONTEXT_STRUCTURE = 'structure';

    private Errors $errors;

    private ValueValidator $values;

    private int $count = 0;

    /** @var array<string, true> */
    private array $seenUuids = [];

    private bool $mayUseAttributes = false;

    private bool $mayUseHtml = false;

    private string $context = self::CONTEXT_PAGE;

    /** @var array<int, bool> global block id => usable */
    private array $globals = [];

    /** @var array<string, true> JSON import asset keys accepted in place of media (not downloaded yet) */
    private array $pendingAssets = [];

    public function __construct(
        private readonly BlockRegistry $registry,
        private readonly SourceRegistry $sources,
        private readonly DisplayModeRegistry $display,
        private readonly HtmlSanitizer $html,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @param  list<array<string, mixed>>  $bindableFields  the custom type's fields (structure context)
     * @return list<array<string, mixed>>
     *
     * @throws ValidationException
     */
    public function validate(array $nodes, ?User $user = null, string $context = self::CONTEXT_PAGE, array $bindableFields = []): array
    {
        $this->errors = new Errors;
        $this->values = new ValueValidator($this->errors, $this->pendingAssets);
        $this->count = 0;
        $this->seenUuids = [];
        $this->globals = [];
        $this->context = $context;
        $this->mayUseAttributes = (bool) $user?->can('blocks.custom_attributes');
        $this->mayUseHtml = (bool) $user?->can('blocks.custom_html');

        if (! array_is_list($nodes)) {
            throw ValidationException::withMessages(['blocks' => __('Invalid block structure.')]);
        }

        $bindings = $context === self::CONTEXT_STRUCTURE ? Bindings::for($bindableFields) : null;
        $clean = $this->nodes($nodes, null, 1, $bindings);

        if ($this->count > (int) config('pacms.blocks.max_nodes')) {
            $this->errors->add('blocks', __('A page can contain at most :max blocks.', ['max' => config('pacms.blocks.max_nodes')]));
        }

        $this->errors->throwIfAny();

        return $clean;
    }

    /**
     * Signature of an HTML block's markup. Only roles with blocks.custom_html can add or change
     * HTML blocks; everyone else may keep, move or delete existing ones unchanged, which the
     * signature (made by the server when a permitted user saved it) proves.
     */
    public static function htmlSignature(string $html): string
    {
        return hash_hmac('sha256', 'pacms-html-block|'.$html, (string) config('app.key'));
    }

    /**
     * @param  array<string, mixed>  $clean  validated content
     * @param  array<string, mixed>  $raw  submitted content (with the signature)
     * @return array<string, mixed>
     */
    private function signedHtml(array $clean, array $raw, string $path): array
    {
        $signature = self::htmlSignature((string) ($clean['html'] ?? ''));

        if (! $this->mayUseHtml && ! hash_equals($signature, (string) ($raw['signature'] ?? ''))) {
            $this->errors->add("{$path}.content.html", __('Only roles with the “HTML blocks” permission can add or change HTML blocks.'));
        }

        return $clean + ['signature' => $signature];
    }

    /**
     * A validator that accepts {"$asset": key} for these keys (JSON import preview).
     *
     * @param  list<string>  $keys
     */
    public function withPendingAssets(array $keys): static
    {
        $clone = clone $this;
        $clone->pendingAssets = array_fill_keys($keys, true);

        return $clone;
    }

    /**
     * @param  array<mixed>  $nodes
     * @param  BlockType|null  $parent  nearest non-transparent parent (null = top level)
     * @return list<array<string, mixed>>
     */
    private function nodes(array $nodes, ?BlockType $parent, int $depth, ?Bindings $bindings): array
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
            if (is_array($node) && ($cleanNode = $this->node($node, $parent, $depth, $bindings)) !== null) {
                $clean[] = $cleanNode;
            }
        }

        return $clean;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>|null
     */
    private function node(array $node, ?BlockType $parent, int $depth, ?Bindings $bindings): ?array
    {
        $this->count++;
        $uuid = $this->uuid($node['uuid'] ?? null);
        $path = "blocks.{$uuid}";
        $type = $this->registry->find((string) ($node['type'] ?? ''));

        if ($type === null) {
            $this->errors->add($path, __('Unknown block type ":type".', ['type' => (string) ($node['type'] ?? '')]));

            return null;
        }

        if (! $type->allowedIn($this->context)) {
            $this->errors->add($path, __(':type cannot be used here.', ['type' => $type->label()]));

            return null;
        }

        // Transparent wrappers (repeat, when) may sit anywhere; their children are checked
        // against the real parent they render into.
        if (! $type->isTransparent() && ! $type->allowedUnder($parent)) {
            $this->errors->add($path, $parent === null
                ? __(':type cannot be placed at page level.', ['type' => $type->label()])
                : __(':type cannot be placed inside :parent.', ['type' => $type->label(), 'parent' => $parent->label()]));

            return null;
        }

        $fieldValidator = new FieldValidator($this->values, $this->html, $bindings);
        $children = (array) ($node['children'] ?? []);

        $clean = array_filter([
            'uuid' => $uuid,
            'type' => $type->slug(),
            'name' => isset($node['name']) && $node['name'] !== '' ? mb_substr(HtmlSanitizer::plain((string) $node['name']), 0, 120) : null,
            'hidden' => ! empty($node['hidden']) ? true : null,
            'global_block_id' => $type->slug() === 'global-ref' ? $this->globalBlock($node['global_block_id'] ?? null, $path) : null,
            'content' => $fieldValidator->validate(array_map(fn ($f) => $f->toArray(), $type->fields()), (array) ($node['content'] ?? []), "{$path}.content"),
            'source' => $this->sources->validate($type, (array) ($node['source'] ?? []), $this->values, "{$path}.source"),
            'display' => $this->display->validate((array) ($node['display'] ?? []), $type->displayModes(), $this->values, "{$path}.display"),
            'layout' => (new StyleValidator($this->values))->layout((array) ($node['layout'] ?? []), "{$path}.layout"),
            'style' => (new StyleValidator($this->values))->style((array) ($node['style'] ?? []), "{$path}.style"),
            'responsive' => (new StyleValidator($this->values))->responsive((array) ($node['responsive'] ?? []), "{$path}.responsive"),
            'advanced' => (new StyleValidator($this->values))->advanced((array) ($node['advanced'] ?? []), "{$path}.advanced", $this->mayUseAttributes),
        ], fn ($value) => $value !== null && $value !== []);

        if ($type->slug() === 'html') {
            $clean['content'] = $this->signedHtml((array) ($clean['content'] ?? []), (array) ($node['content'] ?? []), $path);
        }

        $childBindings = $this->structural($type, (array) ($clean['content'] ?? []), $bindings, $path) ?? $bindings;

        if ($children !== []) {
            if (! $type->acceptsChildren()) {
                $this->errors->add($path, __(':type cannot contain other blocks.', ['type' => $type->label()]));
            } else {
                $clean['children'] = $this->nodes($children, $type->isTransparent() ? $parent : $type, $depth + 1, $childBindings);
            }
        }

        return $clean;
    }

    /**
     * Checks repeat/when settings against the custom type's fields; returns the binding
     * scope for the children of a repeat block.
     *
     * @param  array<string, mixed>  $content
     */
    private function structural(BlockType $type, array $content, ?Bindings $bindings, string $path): ?Bindings
    {
        if ($bindings === null || ! in_array($type->slug(), ['repeat', 'when'], true) || ! isset($content['field'])) {
            return null;
        }

        $field = (string) $content['field'];

        if ($type->slug() === 'repeat') {
            $scope = $bindings->enterRepeat($field);
            if ($scope === null) {
                $this->errors->add("{$path}.content.field", __('Choose a repeater field.'));
            }

            return $scope;
        }

        if ($bindings->resolve($field) === null) {
            $this->errors->add("{$path}.content.field", __('Choose one of the block’s fields.'));
        }
        if (($content['operator'] ?? null) === 'equals' && ! array_key_exists('value', $content)) {
            $this->errors->add("{$path}.content.value", __('Enter the value to compare with (:op).', ['op' => WhenBlock::OPERATORS['equals']]));
        }

        return null;
    }

    private function globalBlock(mixed $id, string $path): ?int
    {
        $id = is_numeric($id) ? (int) $id : 0;

        $this->globals[$id] ??= $id > 0 && GlobalBlock::query()->whereKey($id)->exists();

        if (! $this->globals[$id]) {
            $this->errors->add($path, __('Choose a global block that exists.'));

            return null;
        }

        return $id;
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
