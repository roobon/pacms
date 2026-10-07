<?php

namespace App\Cms\Exchange;

use App\Cms\Blocks\BlockTreeRepository;
use App\Models\BlockTemplate;
use App\Models\BlockType;
use App\Models\GlobalBlock;
use App\Models\Media;
use App\Models\News;
use App\Models\Page;
use App\Models\Term;

/**
 * Internal trees → portable JSON (CMS-BLOCK-SCHEMA.md §18).
 *
 * - media → `assets[]` (strategy "existing", the media id, and the public URL so another
 *   site can switch to "download"; private media has no URL)
 * - entity links, filter terms and global blocks → `$ref` by slug/path
 * - `uuid`s are included; user ids, e-mails and revision history never are
 */
final class Exporter
{
    /** @var array<int, array<string, mixed>> media id => asset entry */
    private array $assets = [];

    /** @var array<string, true> */
    private array $customTypes = [];

    public function __construct(private readonly BlockTreeRepository $tree) {}

    /**
     * @return array<string, mixed>
     */
    public function page(Page $page): array
    {
        $this->reset();
        $page->loadMissing(['seo', 'parent']);
        $seo = $page->seo;

        $data = array_filter([
            'title' => $page->title,
            'slug' => $page->slug,
            'parent' => $page->parent ? ['$ref' => ['entity' => 'pages', 'path' => $page->parent->path]] : null,
            'excerpt' => $page->excerpt,
            'featured_image' => $page->featured_media_id ? $this->media($page->featured_media_id) : null,
            'template' => $page->template,
            'seo' => $seo ? array_filter([
                'title' => $seo->title,
                'description' => $seo->description,
                'robots' => ['index' => (bool) $seo->robots_index, 'follow' => (bool) $seo->robots_follow],
                'og' => array_filter([
                    'title' => $seo->og_title,
                    'description' => $seo->og_description,
                    'image' => $seo->og_image_media_id ? $this->media($seo->og_image_media_id) : null,
                ]),
            ], fn ($v) => $v !== null && $v !== []) : null,
            'blocks' => $this->nodes($this->tree->load($page)),
        ], fn ($v) => $v !== null && $v !== '');

        return $this->envelope('page', $page->title, ['page' => $data]);
    }

    /**
     * @return array<string, mixed>
     */
    public function template(BlockTemplate $template): array
    {
        $this->reset();

        return $this->envelope('template', $template->name, [
            'template' => array_filter([
                'name' => $template->name,
                'slug' => $template->slug,
                'description' => $template->description,
                'scope' => $template->scope,
                'category' => $template->category,
            ]),
            'blocks' => $this->nodes($this->tree->load($template)),
        ]);
    }

    /**
     * Blocks from the builder (already validated): "block" for one node, "section" when
     * every root is a section.
     *
     * @param  list<array<string, mixed>>  $nodes
     * @return array<string, mixed>
     */
    public function blocks(array $nodes, ?string $title = null): array
    {
        $this->reset();
        $kind = $nodes !== [] && collect($nodes)->every(fn ($n) => $n['type'] === 'section') ? 'section' : 'block';

        return $this->envelope($kind, $title, ['blocks' => $this->nodes($nodes)]);
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @return list<array<string, mixed>>
     */
    private function nodes(array $nodes): array
    {
        return array_map(function (array $node): array {
            if (str_starts_with((string) $node['type'], BlockType::CUSTOM_PREFIX)) {
                $this->customTypes[$node['type']] = true;
            }

            $out = ['type' => $node['type'], 'uuid' => $node['uuid']];
            foreach (['name', 'hidden'] as $key) {
                if (! empty($node[$key])) {
                    $out[$key] = $node[$key];
                }
            }

            $content = $this->values((array) ($node['content'] ?? []));
            if ($node['type'] === 'global-ref' && ! empty($node['global_block_id'])) {
                $slug = GlobalBlock::withTrashed()->whereKey($node['global_block_id'])->value('slug');
                $content['global'] = ['$ref' => ['entity' => 'global_blocks', 'slug' => $slug]];
            }
            if ($content !== []) {
                $out['content'] = $content;
            }

            if (! empty($node['source'])) {
                $out['source'] = $this->source((array) $node['source']);
            }
            foreach (['display', 'layout', 'style', 'responsive', 'advanced'] as $section) {
                if (! empty($node[$section])) {
                    $out[$section] = $this->values((array) $node[$section]);
                }
            }
            if (! empty($node['children'])) {
                $out['children'] = $this->nodes($node['children']);
            }

            return $out;
        }, $nodes);
    }

    /**
     * Media and entity links anywhere in a section.
     *
     * @param  array<mixed>  $value
     * @return array<mixed>
     */
    private function values(array $value): array
    {
        foreach ($value as $k => $item) {
            if (! is_array($item)) {
                continue;
            }
            if (isset($item['$media']) && count($item) === 1) {
                $value[$k] = $this->media((int) $item['$media']);
            } elseif (($item['type'] ?? null) === 'entity' && isset($item['entity'], $item['id'])) {
                $value[$k] = $this->link($item);
            } else {
                $value[$k] = $this->values($item);
            }
        }

        return $value;
    }

    /**
     * @return array{'$asset': string}
     */
    private function media(int $id): array
    {
        $key = "media-{$id}";

        if (! isset($this->assets[$id])) {
            $media = Media::query()->find($id);
            $this->assets[$id] = array_filter([
                'key' => $key,
                'media' => $id,
                'url' => $media?->isPublic() ? url($media->url()) : null,
                'alt' => $media?->alt,
                'credit' => $media?->credit,
                'strategy' => 'existing',
            ], fn ($v) => $v !== null && $v !== '');
        }

        return ['$asset' => $key];
    }

    /**
     * @param  array<string, mixed>  $link
     * @return array<string, mixed>
     */
    private function link(array $link): array
    {
        $ref = match ($link['entity']) {
            'pages' => ($page = Page::query()->find($link['id'])) ? ['entity' => 'pages', 'path' => $page->path] : null,
            'news' => ($news = News::query()->find($link['id'])) ? ['entity' => 'news', 'slug' => $news->slug] : null,
            default => null,
        };

        return array_filter([
            'type' => 'entity',
            'ref' => ['$ref' => $ref ?? ['entity' => $link['entity'], 'missing' => true]],
            'new_tab' => ! empty($link['new_tab']) ? true : null,
        ], fn ($v) => $v !== null);
    }

    /**
     * @param  array<string, mixed>  $source
     * @return array<string, mixed>
     */
    private function source(array $source): array
    {
        foreach ((array) ($source['filters'] ?? []) as $name => $value) {
            if (is_numeric($value) && ($term = Term::query()->find((int) $value))) {
                $source['filters'][$name] = ['$ref' => ['entity' => 'terms', 'taxonomy' => $term->taxonomy, 'slug' => $term->slug]];
            }
        }

        return $source;
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function envelope(string $kind, ?string $title, array $body): array
    {
        $meta = array_filter([
            'generator' => 'PACMS 1.0 export',
            'created_at' => now()->toIso8601String(),
            'title' => $title,
            'requires_custom_block_types' => array_keys($this->customTypes) ?: null,
        ]);

        return ['schema_version' => '1.0', 'kind' => $kind, 'meta' => $meta, 'assets' => array_values($this->assets)] + $body;
    }

    private function reset(): void
    {
        $this->assets = [];
        $this->customTypes = [];
    }
}
