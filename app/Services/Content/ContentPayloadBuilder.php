<?php

namespace App\Services\Content;

use App\Cms\Blocks\BlockPayloadResolver;
use App\Cms\Blocks\BlockTreeRepository;
use App\Cms\Content\ContentType;
use App\Cms\Content\ContentTypeRegistry;
use App\Cms\Content\Types\GalleryType;
use App\Models\Attachment;
use App\Models\ContentItem;
use App\Models\Gallery;
use App\Models\GlobalBlock;
use App\Models\SeoMetadata;
use App\Models\Term;
use App\Services\Cache\CacheVersions;
use App\Services\Seo\SeoResolver;
use App\Services\Settings\SettingsService;
use App\Support\Html\HtmlSanitizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Public payloads for content modules (API-ARCHITECTURE.md §2): an item's detail page and
 * a module's archive (paginated list with category filter and views such as upcoming/past).
 */
class ContentPayloadBuilder
{
    public const PER_PAGE = 12;

    public function __construct(
        private readonly ContentTypeRegistry $types,
        private readonly SeoResolver $seo,
        private readonly SettingsService $settings,
        private readonly CacheVersions $versions,
        private readonly BlockPayloadResolver $blocks,
        private readonly BlockTreeRepository $tree,
        private readonly HtmlSanitizer $html,
    ) {}

    /**
     * Detail payload of a published item (cached until the module, media, settings,
     * global blocks or block types change).
     *
     * @return array<string, mixed>
     */
    public function item(ContentItem $item): array
    {
        $type = $this->types->forModel($item);
        $key = 'pacms:content:'.$type->key().':'.$item->getKey().':'.$item->updated_at?->timestamp.':'
            .$this->versions->fingerprint($type->key(), 'media', 'settings', 'globals', 'block_types', ...$this->otherTypes($type));

        return Cache::remember($key, now()->addDay(), fn () => $this->build($type, $item));
    }

    /**
     * Payload for an admin preview (never cached).
     *
     * @return array<string, mixed>
     */
    public function preview(ContentItem $item): array
    {
        return $this->build($this->types->forModel($item), $item) + ['preview' => true];
    }

    /**
     * Archive for the query string of a public request (?view=past&category=climate&page=2).
     * Only plain string values count, so crafted arrays fall back to the defaults.
     *
     * @return array<string, mixed>
     */
    public function archiveForRequest(ContentType $type, Request $request): array
    {
        $text = fn (string $key) => is_string($value = $request->query($key)) ? mb_substr($value, 0, 191) : null;

        return $this->archive($type, $text('view'), $text('category'), min(10000, max(1, (int) $text('page'))));
    }

    /**
     * @return array<string, mixed>
     */
    public function archive(ContentType $type, ?string $view = null, ?string $category = null, int $page = 1): array
    {
        $model = $type->modelClass();
        $query = $model::query()->published()->with(array_filter(['featuredMedia', $type->taxonomy() ? 'terms' : null]));

        $views = $type->archiveViews();
        $view = $views === [] ? null : (isset($views[(string) $view]) ? $view : array_key_first($views));
        $type->applyArchiveView($query, $view);

        $categories = $type->taxonomy() === null ? collect() : Term::query()->inTaxonomy((string) $type->taxonomy())->orderBy('name')->get(['id', 'name', 'slug']);
        $current = $category ? $categories->firstWhere('slug', $category) : null;
        if ($current !== null) {
            $query->whereHas('terms', fn ($terms) => $terms->whereKey($current->id));
        }

        $results = $query->paginate(self::PER_PAGE, ['*'], 'page', max(1, $page));
        $site = (string) $this->settings->get('site', 'name');
        $url = $this->seo->absolute('/'.$type->routePrefix());
        $breadcrumbs = [['title' => $site, 'url' => '/'], ['title' => $type->label(), 'url' => '/'.$type->routePrefix()]];

        return [
            'type' => $type->key(),
            'title' => $type->label(),
            'path' => '/'.$type->routePrefix(),
            'views' => $views,
            'view' => $view,
            'categories' => $categories->map(fn (Term $term) => ['name' => $term->name, 'slug' => $term->slug])->values(),
            'category' => $current?->slug,
            'items' => collect($results->items())->map(fn (ContentItem $item) => $item->toItem())->values(),
            'pagination' => ['page' => $results->currentPage(), 'pages' => $results->lastPage(), 'total' => $results->total()],
            'breadcrumbs' => $breadcrumbs,
            'seo' => $this->seo->resolve(['title' => $type->label(), 'excerpt' => null, 'url' => $url], null, $breadcrumbs),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function build(ContentType $type, ContentItem $item): array
    {
        $item->loadMissing(['featuredMedia', 'seo', ...($type->taxonomy() ? ['terms'] : [])]);
        $site = (string) $this->settings->get('site', 'name');
        $url = $this->seo->absolute($item->url());
        $breadcrumbs = [
            ['title' => $site, 'url' => '/'],
            ['title' => $type->label(), 'url' => '/'.$type->routePrefix()],
            ['title' => $item->title, 'url' => $item->url()],
        ];
        $excerpt = HtmlSanitizer::toText($item->excerpt);

        $seo = $this->seo->resolve([
            'title' => $item->title,
            'excerpt' => $excerpt,
            'url' => $url,
            'image' => $item->featuredMedia,
            'type' => 'article',
        ], $item->seo?->only(SeoMetadata::FIELDS), $breadcrumbs);
        if (($jsonLd = $type->jsonLd($item, $seo, $site)) !== null) {
            $seo['json_ld'][] = $jsonLd;
        }

        $categories = $type->taxonomy() === null ? [] : $item->terms
            ->where('taxonomy', $type->taxonomy())
            ->map(fn (Term $term) => ['name' => $term->name, 'slug' => $term->slug])->values()->all();

        return [
            'type' => $type->key(),
            'type_label' => $type->label(),
            'archive_path' => '/'.$type->routePrefix(),
            'id' => $item->getKey(),
            'title' => $item->title,
            'path' => $item->url(),
            'url' => $url,
            'excerpt' => $excerpt,
            'excerpt_html' => $excerpt === null ? null : $this->html->inline((string) $item->excerpt),
            // Cleaned on save; cleaned again here so older rows are safe too.
            'body' => $item->body ? ($this->html->sanitize((string) $item->body) ?: null) : null,
            'featured_image' => $item->featuredMedia?->toImageArray('(min-width: 1320px) 1280px, 100vw'),
            'category' => $categories[0]['name'] ?? null,
            'categories' => $categories,
            'published_at' => $item->published_at?->toIso8601String(),
            'show_date' => $type->showsPublishDate(),
            'breadcrumbs' => $breadcrumbs,
            'seo' => $seo,
            'blocks' => $this->blocks->resolve($this->tree->load($item), includeHidden: false),
            'sidebar' => $this->sidebar($type, $item),
            'documents' => $this->documents($type, $item),
        ] + $this->withRelations($type, $item, $type->details($item));
    }

    /**
     * Adds what relation fields link to: names as facts (with links), logo or card rows,
     * and an embedded gallery.
     *
     * @param  array<string, mixed>  $details
     * @return array<string, mixed>
     */
    private function withRelations(ContentType $type, ContentItem $item, array $details): array
    {
        $related = [];
        foreach ($type->relationFields() as $name => $field) {
            $items = $item->related($name);
            if ($items === []) {
                continue;
            }
            $title = (string) ($field['title'] ?? $field['label']);
            $display = (string) ($field['display'] ?? 'cards');

            if ($display === 'fact') {
                foreach ($items as $linked) {
                    $details['facts'][] = [
                        'icon' => $linked->type()->icon(),
                        'label' => $title,
                        'value' => $linked->title,
                        'url' => $linked->type()->hasDetailPages() ? $linked->url() : null,
                    ];
                }
            } elseif ($display === 'gallery') {
                $gallery = $items[0];
                if ($gallery instanceof Gallery && ($media = app(GalleryType::class)->media($gallery)) !== []) {
                    $related[] = ['key' => $name, 'title' => $title, 'display' => 'gallery', 'items' => $media, 'url' => $gallery->url(), 'link_label' => $gallery->title];
                }
            } else {
                $related[] = ['key' => $name, 'title' => $title, 'display' => $display, 'items' => array_map(fn (ContentItem $linked) => $linked->toItem(), $items)];
            }
        }

        return $details + ['related' => $related];
    }

    /**
     * Downloadable documents (public files only).
     *
     * @return list<array{label: string, url: string, extension: string, size: string}>
     */
    private function documents(ContentType $type, ContentItem $item): array
    {
        if (! $type->documents()) {
            return [];
        }

        return $item->attachments()->with('media')->get()
            ->filter(fn (Attachment $attachment) => $attachment->media?->isPublic() === true)
            ->map(fn (Attachment $attachment) => [
                'label' => $attachment->label ?: (string) $attachment->media->original_name,
                'url' => (string) $attachment->media->url(),
                'extension' => strtoupper((string) $attachment->media->extension),
                'size' => $attachment->media->humanSize(),
            ])
            ->values()->all();
    }

    /**
     * The item's sidebar: its own choice, else the module default (Settings), else none.
     *
     * @return array{position: string, blocks: list<array<string, mixed>>}|null
     */
    private function sidebar(ContentType $type, ContentItem $item): ?array
    {
        $defaults = (array) ($this->settings->get('content', 'sidebars')[$type->key()] ?? []);
        $id = match ($item->getAttribute('sidebar_mode')) {
            'none' => null,
            'custom' => $item->getAttribute('sidebar_global_block_id'),
            default => $defaults['global_block_id'] ?? null,
        };

        if (! $id || ! GlobalBlock::query()->whereKey((int) $id)->whereNotNull('published_revision_id')->exists()) {
            return null;
        }

        $blocks = $this->blocks->resolve([[
            'uuid' => strtolower(substr(Str::of(hash('sha256', 'sidebar|'.$type->key().'|'.$item->getKey()))->toString(), 0, 26)),
            'type' => 'global-ref',
            'global_block_id' => (int) $id,
        ]]);

        return $blocks === [] ? null : ['position' => ($defaults['position'] ?? 'right') === 'left' ? 'left' : 'right', 'blocks' => $blocks];
    }

    /**
     * Collection blocks inside an item may list other modules, so their versions matter too.
     *
     * @return list<string>
     */
    private function otherTypes(ContentType $type): array
    {
        return array_values(array_diff(array_keys($this->types->all()), [$type->key()]));
    }
}
