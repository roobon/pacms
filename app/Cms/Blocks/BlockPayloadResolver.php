<?php

namespace App\Cms\Blocks;

use App\Cms\Fields\VideoUrl;
use App\Cms\Sources\SourceRegistry;
use App\Models\Media;
use App\Models\News;
use App\Models\Page;

/**
 * Turns stored block nodes into the public payload (API-ARCHITECTURE.md §2.2):
 * media references become responsive image objects, links become safe hrefs, dynamic
 * and external sources become normalised `items`. Lookups are batched (no N+1).
 */
class BlockPayloadResolver
{
    /** @var array<int, Media> */
    private array $media = [];

    /** @var array<string, array<int, string|null>> entity => id => url */
    private array $links = [];

    public function __construct(
        private readonly BlockRegistry $registry,
        private readonly BlockTreeRepository $tree,
        private readonly SourceRegistry $sources,
        private readonly BlockExpander $expander,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @param  bool  $includeHidden  keep hidden blocks (flagged) for the builder preview
     * @return list<array<string, mixed>>
     */
    public function resolve(array $nodes, bool $includeHidden = false): array
    {
        // Global blocks and custom blocks become ordinary blocks first, so their media and
        // links are resolved in the same batches.
        $nodes = $this->expander->expand($nodes);

        $this->media = $this->tree->referencedMedia($nodes);
        $this->links = $this->collectLinks($nodes);

        return $this->nodes($nodes, $includeHidden);
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @return list<array<string, mixed>>
     */
    private function nodes(array $nodes, bool $includeHidden): array
    {
        $resolved = [];

        foreach ($nodes as $node) {
            if (! empty($node['hidden']) && ! $includeHidden) {
                continue;
            }

            $type = $this->registry->find((string) $node['type']);
            if ($type === null) {
                continue;
            }

            $fields = array_map(fn ($field) => $field->toArray(), $type->fields());
            $out = [
                'uuid' => $node['uuid'],
                'type' => $node['type'],
                'content' => $this->fields($fields, (array) ($node['content'] ?? [])),
                'display' => $node['display'] ?? (object) [],
                'layout' => $node['layout'] ?? (object) [],
                'style' => $this->style((array) ($node['style'] ?? [])),
                'responsive' => $this->responsive((array) ($node['responsive'] ?? [])),
                'advanced' => $node['advanced'] ?? (object) [],
            ];

            if (! empty($node['hidden'])) {
                $out['hidden'] = true;
            }
            if (! empty($node['locked'])) {
                $out['locked'] = true;
            }

            $source = (array) ($node['source'] ?? []);
            $items = $this->sources->items($source);
            if ($items !== null) {
                $out['source'] = ['mode' => $source['mode']];
                $out['items'] = $items;
            } elseif (array_key_exists('items', $out['content']) && in_array('static', $type->sourceModes(), true) && count($type->sourceModes()) > 1) {
                $out['source'] = ['mode' => 'static'];
                $out['items'] = $this->staticItems($type->slug(), (array) $out['content']['items']);
            }

            if (! empty($node['children'])) {
                $out['children'] = $this->nodes($node['children'], $includeHidden);
            }

            $resolved[] = $out;
        }

        return $resolved;
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function fields(array $fields, array $values): array
    {
        $out = [];

        foreach ($fields as $field) {
            $key = $field['key'];
            if (! array_key_exists($key, $values)) {
                continue;
            }
            $value = $values[$key];

            $out[$key] = match ($field['type']) {
                'image' => $this->image($value),
                'media' => $this->file($value),
                'link' => $this->link($value),
                'video-url' => $this->video((string) $value),
                'repeater' => array_map(fn ($row) => $this->fields($field['fields'], (array) $row), (array) $value),
                default => $value,
            };
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $style
     * @return array<string, mixed>|object
     */
    private function style(array $style): array|object
    {
        if (isset($style['background']['image'])) {
            $media = $this->media[(int) ($style['background']['image']['$media'] ?? 0)] ?? null;
            $style['background']['image'] = $media?->isImage() ? [
                'src' => $media->url(),
                'srcset' => $media->toImageArray()['srcset'] ?? null,
                'focal_point' => $media->focal_point,
            ] : null;
        }

        return $style === [] ? (object) [] : $style;
    }

    /**
     * @param  array<string, mixed>  $responsive
     * @return array<string, mixed>|object
     */
    private function responsive(array $responsive): array|object
    {
        foreach ($responsive as $breakpoint => $values) {
            if (isset($values['style'])) {
                $responsive[$breakpoint]['style'] = $this->style((array) $values['style']);
            }
        }

        return $responsive === [] ? (object) [] : $responsive;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function image(mixed $value): ?array
    {
        $media = $this->media[(int) ($value['$media'] ?? 0)] ?? null;

        return $media?->toImageArray('(min-width: 1320px) 1280px, 100vw');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function file(mixed $value): ?array
    {
        $media = $this->media[(int) ($value['$media'] ?? 0)] ?? null;

        if ($media === null || ! $media->isPublic()) {
            return null;
        }

        return $media->isImage()
            ? $media->toImageArray()
            : ['url' => $media->url(), 'name' => $media->original_name, 'kind' => $media->kind->value, 'size' => $media->humanSize()];
    }

    /**
     * Safe, render-ready link: {href, new_tab, external} or null when the target is gone.
     *
     * @return array{href: string, new_tab: bool, external: bool}|null
     */
    private function link(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $href = match ($value['type'] ?? null) {
            'url' => $value['url'] ?? null,
            'entity' => $this->links[$value['entity'] ?? ''][(int) ($value['id'] ?? 0)] ?? null,
            'anchor' => '#'.($value['anchor'] ?? ''),
            'email' => 'mailto:'.($value['email'] ?? ''),
            default => null,
        };

        if (! is_string($href) || $href === '') {
            return null;
        }

        $external = (bool) preg_match('#^https?://#i', $href)
            && parse_url($href, PHP_URL_HOST) !== parse_url((string) config('app.url'), PHP_URL_HOST);

        return ['href' => $href, 'new_tab' => (bool) ($value['new_tab'] ?? false), 'external' => $external];
    }

    /**
     * @return array{provider: string, id: string, url: string, thumbnail: string|null}|null
     */
    private function video(string $url): ?array
    {
        $parsed = VideoUrl::parse($url);

        if ($parsed === null) {
            return null;
        }

        return $parsed + [
            'url' => $url,
            'thumbnail' => $parsed['provider'] === 'youtube' ? "https://i.ytimg.com/vi/{$parsed['id']}/hqdefault.jpg" : null,
        ];
    }

    /**
     * Static repeater rows in the normalised item shape, so display modes are source-agnostic.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function staticItems(string $kind, array $rows): array
    {
        return array_values(array_map(fn ($row, $i) => [
            'key' => "{$kind}:static:{$i}",
            'kind' => $kind,
            'title' => $row['title'] ?? '',
            'url' => $row['link']['href'] ?? null,
            'link' => $row['link'] ?? null,
            'external' => $row['link']['external'] ?? false,
            'excerpt' => $row['excerpt'] ?? null,
            'image' => $row['image'] ?? null,
            'date' => $row['date'] ?? null,
            'meta' => [],
        ], $rows, array_keys($rows)));
    }

    /**
     * Resolve every entity link target once: entity => id => public URL (null if not public).
     *
     * @param  list<array<string, mixed>>  $nodes
     * @return array<string, array<int, string|null>>
     */
    private function collectLinks(array $nodes): array
    {
        $ids = ['pages' => [], 'news' => []];

        $walk = function (mixed $value) use (&$walk, &$ids) {
            if (! is_array($value)) {
                return;
            }
            if (($value['type'] ?? null) === 'entity' && isset($ids[$value['entity'] ?? ''])) {
                $ids[$value['entity']][] = (int) ($value['id'] ?? 0);
            }
            foreach ($value as $child) {
                $walk($child);
            }
        };
        $walk($nodes);

        $links = ['pages' => [], 'news' => []];

        if ($ids['pages'] !== []) {
            foreach (Page::query()->whereKey(array_unique($ids['pages']))->get() as $page) {
                $links['pages'][$page->id] = $page->publicUrl();
            }
        }

        if ($ids['news'] !== []) {
            foreach (News::query()->whereKey(array_unique($ids['news']))->get() as $news) {
                $links['news'][$news->id] = $news->isPublished() ? $news->url() : null;
            }
        }

        return $links;
    }
}
