<?php

namespace App\Cms\Exchange;

use App\Cms\Blocks\BlockRegistry;
use App\Cms\Blocks\BlockType;
use App\Cms\Content\ContentTypeRegistry;
use App\Models\ContentItem;
use App\Models\GlobalBlock;
use App\Models\Page;
use App\Models\Term;
use App\Support\Html\HtmlSanitizer;
use Illuminate\Support\Str;

/**
 * Portable JSON (CMS-BLOCK-SCHEMA.md) → the builder's internal node format.
 *
 * - unknown block types and properties are skipped and reported
 * - `$ref`s by slug/path become ids (pages, news, terms, global blocks); missing → reported
 * - `{"$asset": key}` stays as a pending asset until the import downloads or maps it
 * - select values are normalised to strings (an AI writing "level": 1 means "1")
 *
 * The result is then checked by the normal BlockTreeValidator (ImportCleaner).
 */
final class PortableTranslator
{
    private const NODE_KEYS = ['type', 'key', 'uuid', 'name', 'hidden', 'source', 'content', 'display', 'layout', 'style', 'responsive', 'advanced', 'children'];

    private ImportReport $report;

    /** @var array<string, true> declared asset keys */
    private array $assets = [];

    /** @var array<string, array{pointer: string, key: string|null}> uuid => where it came from */
    private array $origins = [];

    /** @var array<string, true> */
    private array $keys = [];

    public function __construct(
        private readonly BlockRegistry $registry,
        private readonly HtmlSanitizer $html,
    ) {}

    /**
     * @param  list<mixed>  $nodes
     * @param  list<string>  $assetKeys
     * @return array{nodes: list<array<string, mixed>>, origins: array<string, array{pointer: string, key: string|null}>}
     */
    public function nodes(array $nodes, string $pointer, array $assetKeys, ImportReport $report): array
    {
        $this->report = $report;
        $this->assets = array_fill_keys($assetKeys, true);
        $this->origins = [];
        $this->keys = [];

        return ['nodes' => $this->list($nodes, $pointer), 'origins' => $this->origins];
    }

    /**
     * Translate a media value used outside blocks (featured image, social image).
     *
     * @param  list<string>  $assetKeys
     * @return array<string, mixed>|null
     */
    public function media(mixed $value, string $pointer, array $assetKeys, ImportReport $report): ?array
    {
        $this->report = $report;
        $this->assets = array_fill_keys($assetKeys, true);

        return $this->mediaValue($value, $pointer);
    }

    /**
     * A page by "path" ("about/team") or "slug" reference; null when missing.
     *
     * @param  array<string, mixed>  $ref
     */
    public static function findPage(array $ref): ?Page
    {
        if (isset($ref['path']) && is_string($ref['path'])) {
            return Page::query()->where('path', trim($ref['path'], '/'))->first();
        }

        return isset($ref['slug']) && is_string($ref['slug']) ? Page::query()->where('slug', $ref['slug'])->orderBy('id')->first() : null;
    }

    /**
     * @param  list<mixed>  $nodes
     * @return list<array<string, mixed>>
     */
    private function list(array $nodes, string $pointer): array
    {
        $out = [];
        foreach (array_values($nodes) as $index => $node) {
            $translated = $this->node($node, "{$pointer}/{$index}");
            if ($translated !== null) {
                $out[] = $translated;
            }
        }

        return $out;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function node(mixed $node, string $pointer): ?array
    {
        if (! is_array($node) || array_is_list($node)) {
            $this->report->warning('blocks', __('Not a block object; skipped.'), $pointer);

            return null;
        }

        $key = $this->key($node['key'] ?? null, $pointer);
        $slug = is_string($node['type'] ?? null) ? $node['type'] : '';
        $type = $this->registry->find($slug);

        if ($type === null) {
            $this->report->warning('blocks', $slug === ''
                ? __('A block without "type" was skipped.')
                : __('Block type ":type" is not available on this site; the block was skipped.', ['type' => $slug]), $pointer, $key);

            return null;
        }

        foreach (array_diff(array_keys($node), self::NODE_KEYS) as $unknown) {
            $this->report->warning('unsupported', __('Unsupported property ":name" was ignored.', ['name' => $unknown]), "{$pointer}/{$unknown}", $key);
        }

        $uuid = strtolower((string) Str::ulid());
        $this->origins[$uuid] = ['pointer' => $pointer, 'key' => $key];

        $out = ['uuid' => $uuid, 'type' => $type->slug()];
        if (is_scalar($node['name'] ?? null) && (string) $node['name'] !== '') {
            $out['name'] = (string) $node['name'];
        }
        if (! empty($node['hidden'])) {
            $out['hidden'] = true;
        }

        $content = is_array($node['content'] ?? null) ? $node['content'] : [];
        if ($type->slug() === 'global-ref') {
            $out['global_block_id'] = $this->globalBlock($content['global'] ?? null, "{$pointer}/content/global", $key);
            unset($content['global']);
        }

        $fields = array_column(array_map(fn ($f) => $f->toArray(), $type->fields()), null, 'key');
        $out['content'] = $this->content($fields, $content, "{$pointer}/content", $key);

        if (is_array($node['source'] ?? null)) {
            $out['source'] = $this->source($type, $node['source'], "{$pointer}/source", $key);
        }
        foreach (['display', 'layout'] as $section) {
            if (is_array($node[$section] ?? null)) {
                $out[$section] = $node[$section];
            }
        }
        if (is_array($node['style'] ?? null)) {
            $out['style'] = $this->style($node['style'], "{$pointer}/style");
        }
        if (is_array($node['responsive'] ?? null)) {
            $out['responsive'] = $this->responsive($node['responsive'], "{$pointer}/responsive", $key);
        }
        if (is_array($node['advanced'] ?? null)) {
            $out['advanced'] = $this->advanced($node['advanced'], "{$pointer}/advanced", $key);
        }
        if (is_array($node['children'] ?? null) && $node['children'] !== []) {
            $out['children'] = $this->list($node['children'], "{$pointer}/children");
        }

        return $out;
    }

    /**
     * @param  array<string, array<string, mixed>>  $fields
     * @param  array<mixed>  $content
     * @return array<string, mixed>
     */
    private function content(array $fields, array $content, string $pointer, ?string $key): array
    {
        $out = [];

        // AI tools often write a block's link as a plain "url" (or "href") next to "label":
        // when the block has exactly one link field and it is missing, use that address.
        $links = array_keys(array_filter($fields, fn (array $field) => ($field['type'] ?? null) === 'link'));
        foreach (['url', 'href'] as $shorthand) {
            if (count($links) === 1 && ! isset($fields[$shorthand]) && ! array_key_exists($links[0], $content) && is_string($content[$shorthand] ?? null)) {
                $content[$links[0]] = ['type' => 'url', 'url' => $content[$shorthand]];
                unset($content[$shorthand]);
                $this->report->info('blocks', __('"'.$shorthand.'" was read as the block\'s link.'), "{$pointer}/{$shorthand}", $key);
            }
        }

        foreach ($content as $name => $value) {
            $field = $fields[$name] ?? null;
            if ($field === null) {
                $this->report->warning('unsupported', __('Unsupported setting ":name" was ignored.', ['name' => $name]), "{$pointer}/{$name}", $key);

                continue;
            }

            $translated = $this->value($field, $value, "{$pointer}/{$name}", $key);
            if ($translated !== null) {
                $out[$name] = $translated;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function value(array $field, mixed $value, string $pointer, ?string $key): mixed
    {
        return match ($field['type']) {
            'select', 'radio' => is_bool($value) ? ($value ? '1' : '0') : (is_scalar($value) ? (string) $value : $value),
            'multi-select' => is_array($value) ? array_map(fn ($v) => is_scalar($v) ? (string) $v : $v, $value) : $value,
            'image', 'media' => $this->mediaValue($value, $pointer, $key),
            'link' => $this->link($value, $pointer, $key),
            'rich-text' => $this->richText($value, $pointer, $key),
            'url' => is_array($value) && ($value['type'] ?? null) === 'url' ? ($value['url'] ?? null) : $value,
            'repeater' => is_array($value)
                ? array_values(array_map(
                    fn ($row, $i) => $this->content(array_column($field['fields'] ?? [], null, 'key'), is_array($row) ? $row : [], "{$pointer}/{$i}", $key),
                    $value,
                    array_keys($value),
                ))
                : $value,
            default => $value,
        };
    }

    /**
     * Rich text is cleaned when saved; report up front when that removes anything.
     */
    private function richText(mixed $value, string $pointer, ?string $key): mixed
    {
        if (is_string($value)) {
            $squash = fn (string $html) => (string) preg_replace('/\s+/', '', html_entity_decode($html));
            if ($squash($this->html->sanitize($value)) !== $squash($value)) {
                $this->report->warning('security', __('The HTML was cleaned: only safe formatting is kept (scripts, styles, event handlers and unsafe links are removed).'), $pointer, $key);
            }
        }

        return $value;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function mediaValue(mixed $value, string $pointer, ?string $key = null): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        if (isset($value['$asset'])) {
            if (is_string($value['$asset']) && isset($this->assets[$value['$asset']])) {
                return ['$asset' => $value['$asset']];
            }
            $this->report->warning('references', __('Asset ":asset" is not declared in "assets"; the image was left empty.', ['asset' => (string) json_encode($value['$asset'])]), $pointer, $key);

            return null;
        }

        return $value; // {"$media": id} — checked by the validator; missing ids are reported, never substituted.
    }

    /**
     * Portable link (§8.4) → internal link. Entity links by slug/path become ids.
     *
     * @return array<string, mixed>|null
     */
    private function link(mixed $value, string $pointer, ?string $key): ?array
    {
        if (is_string($value)) {
            return ['type' => 'url', 'url' => $value];
        }
        if (! is_array($value) || ($value['type'] ?? null) !== 'entity' || ! isset($value['ref'])) {
            return is_array($value) ? $value : null;
        }

        $ref = (array) ($value['ref']['$ref'] ?? []);
        $entity = (string) ($ref['entity'] ?? '');
        $type = app(ContentTypeRegistry::class)->find($entity);
        $target = match (true) {
            $entity === 'pages' => self::findPage($ref),
            $type !== null => is_string($ref['slug'] ?? null) ? $type->query()->where('slug', $ref['slug'])->first() : null,
            default => null,
        };

        if ($target === null) {
            $this->report->warning('references', ($entity === 'pages' || $type !== null)
                ? __('Link target :ref was not found; the link was removed.', ['ref' => (string) json_encode($ref, JSON_UNESCAPED_SLASHES)])
                : __('Links to ":entity" are not available yet; the link was removed.', ['entity' => $entity]), $pointer, $key);

            return null;
        }

        if ($target instanceof Page && ! $target->isLive() || $target instanceof ContentItem && ! $target->isPublished()) {
            $this->report->info('references', __('Link target :ref is not published yet; the link shows as plain text until it is.', ['ref' => (string) json_encode($ref, JSON_UNESCAPED_SLASHES)]), $pointer, $key);
        }

        return ['type' => 'entity', 'entity' => $entity, 'id' => $target->getKey(), 'new_tab' => (bool) ($value['new_tab'] ?? false)];
    }

    /**
     * @param  array<mixed>  $source
     * @return array<mixed>
     */
    private function source(BlockType $type, array $source, string $pointer, ?string $key): array
    {
        if (($source['mode'] ?? null) === 'external') {
            $ref = $source['source']['$ref'] ?? null;
            $this->report->warning('external', __('External source :ref must be set up by an administrator (external providers arrive in Phase 10). The block shows its fallback for now.', [
                'ref' => is_array($ref) ? (string) json_encode($ref, JSON_UNESCAPED_SLASHES) : '(none)',
            ]), $pointer, $key);

            return [];
        }

        foreach ((array) ($source['filters'] ?? []) as $name => $filter) {
            if (is_array($filter) && isset($filter['$ref'])) {
                $ref = (array) $filter['$ref'];
                $term = ($ref['entity'] ?? null) === 'terms'
                    ? Term::query()->where('taxonomy', (string) ($ref['taxonomy'] ?? ''))->where('slug', (string) ($ref['slug'] ?? ''))->first()
                    : null;
                if ($term === null) {
                    $this->report->warning('references', __('Filter value :ref was not found; the filter was removed.', ['ref' => (string) json_encode($ref, JSON_UNESCAPED_SLASHES)]), "{$pointer}/filters/{$name}", $key);
                    unset($source['filters'][$name]);
                } else {
                    $source['filters'][$name] = $term->id;
                }
            }
        }

        if (isset($source['pick'])) {
            $this->report->warning('unsupported', __('Hand-picked items ("pick") are not supported yet; the newest items are shown instead.'), "{$pointer}/pick", $key);
            unset($source['pick']);
            if (($source['order'] ?? null) === 'manual') {
                $source['order'] = 'latest';
            }
        }

        if (($source['mode'] ?? null) === 'dynamic' && ! isset($source['entity']) && $type->dynamicEntity() !== null) {
            $source['entity'] = $type->dynamicEntity();
        }

        return $source;
    }

    /**
     * @param  array<mixed>  $style
     * @return array<mixed>
     */
    private function style(array $style, string $pointer): array
    {
        if (isset($style['background']['image'])) {
            $image = $this->mediaValue($style['background']['image'], "{$pointer}/background/image");
            if ($image === null) {
                unset($style['background']['image']);
            } else {
                $style['background']['image'] = $image;
            }
        }

        return $style;
    }

    /**
     * @param  array<mixed>  $responsive
     * @return array<mixed>
     */
    private function responsive(array $responsive, string $pointer, ?string $key): array
    {
        $out = [];
        foreach ($responsive as $device => $values) {
            if (! in_array($device, ['tablet', 'mobile'], true) || ! is_array($values)) {
                $this->report->warning('unsupported', __('Unsupported device ":device" was ignored (use tablet or mobile).', ['device' => $device]), "{$pointer}/{$device}", $key);

                continue;
            }
            foreach (array_diff(array_keys($values), ['layout', 'style']) as $unknown) {
                $this->report->warning('unsupported', __('":name" cannot be set per device; ignored. Use advanced.visibility.hide_on to hide a block on a device.', ['name' => $unknown]), "{$pointer}/{$device}/{$unknown}", $key);
            }
            $out[$device] = array_filter([
                'layout' => is_array($values['layout'] ?? null) ? $values['layout'] : null,
                'style' => is_array($values['style'] ?? null) ? $this->style($values['style'], "{$pointer}/{$device}/style") : null,
            ]);
        }

        return $out;
    }

    /**
     * @param  array<mixed>  $advanced
     * @return array<mixed>
     */
    private function advanced(array $advanced, string $pointer, ?string $key): array
    {
        if (array_key_exists('custom_css', $advanced)) {
            $this->report->warning('security', __('Custom CSS is not accepted from imports; it was removed.'), "{$pointer}/custom_css", $key);
            unset($advanced['custom_css']);
        }
        if (array_key_exists('audience', $advanced['visibility'] ?? [])) {
            $this->report->warning('unsupported', __('Audience visibility is not supported yet; ignored.'), "{$pointer}/visibility/audience", $key);
            unset($advanced['visibility']['audience']);
        }

        return $advanced;
    }

    private function globalBlock(mixed $value, string $pointer, ?string $key): ?int
    {
        $ref = is_array($value) ? (array) ($value['$ref'] ?? []) : [];
        $global = ($ref['entity'] ?? null) === 'global_blocks' && is_string($ref['slug'] ?? null)
            ? GlobalBlock::query()->where('slug', $ref['slug'])->first()
            : null;

        if ($global === null) {
            $this->report->warning('references', __('Global block :ref was not found; the block was removed.', ['ref' => (string) json_encode($ref ?: $value, JSON_UNESCAPED_SLASHES)]), $pointer, $key);
        }

        return $global?->id;
    }

    private function key(mixed $key, string $pointer): ?string
    {
        if ($key === null) {
            return null;
        }

        if (! is_string($key) || ! preg_match('/^[A-Za-z0-9_-]{1,64}$/', $key)) {
            $this->report->warning('unsupported', __('Block "key" must use letters, digits, - and _ (max 64); ignored.'), "{$pointer}/key");

            return null;
        }

        if (isset($this->keys[$key])) {
            $this->report->warning('blocks', __('The key ":key" is used more than once.', ['key' => $key]), "{$pointer}/key", $key);
        }
        $this->keys[$key] = true;

        return $key;
    }
}
