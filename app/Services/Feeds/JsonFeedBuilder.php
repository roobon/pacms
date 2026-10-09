<?php

namespace App\Services\Feeds;

use App\Cms\Content\ContentType;
use App\Cms\Content\ContentTypeRegistry;
use App\Models\ContentItem;
use App\Models\Media;
use App\Models\Term;
use App\Services\Cache\CacheVersions;
use App\Services\Seo\SeoResolver;
use App\Services\Settings\SettingsService;
use App\Support\Html\HtmlSanitizer;
use Illuminate\Support\Facades\Cache;

/**
 * PACMS's own feeds in JSON Feed 1.1 (D-15, CMS-ARCHITECTURE.md §16.1): the newest
 * published items of the main modules (/feed.json) or of one module (/feed/{type}.json,
 * optionally one category). Published items only, at most 50, cached until content changes.
 */
class JsonFeedBuilder
{
    public const LIMIT = 50;

    /** Modules in the combined feed. */
    public const COMBINED = ['news', 'events', 'projects', 'programs', 'publications'];

    public function __construct(
        private readonly ContentTypeRegistry $types,
        private readonly SettingsService $settings,
        private readonly SeoResolver $seo,
        private readonly HtmlSanitizer $html,
        private readonly CacheVersions $versions,
    ) {}

    /**
     * Modules that have their own feed: those with a listing and item pages.
     *
     * @return array<string, ContentType>
     */
    public function feedTypes(): array
    {
        return array_filter($this->types->all(), fn (ContentType $type) => $type->hasArchive() && $type->hasDetailPages());
    }

    /**
     * @return array<string, mixed>
     */
    public function combined(): array
    {
        $types = array_values(array_intersect_key($this->feedTypes(), array_flip(self::COMBINED)));
        $key = 'pacms:feed:all:'.$this->versions->fingerprint('settings', 'media', ...array_map(fn ($type) => $type->key(), $types));

        return Cache::remember($key, now()->addDay(), function () use ($types) {
            $items = [];
            foreach ($types as $type) {
                foreach ($this->latest($type, null) as $item) {
                    $items[] = [$item->published_at?->getTimestamp() ?? 0, $this->item($type, $item)];
                }
            }
            usort($items, fn ($a, $b) => $b[0] <=> $a[0]);

            return $this->feed('/feed.json', '/', null, array_column(array_slice($items, 0, self::LIMIT), 1));
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function forType(ContentType $type, ?Term $category): array
    {
        $key = 'pacms:feed:'.$type->key().':'.($category->id ?? 0).':'.$this->versions->fingerprint($type->key(), 'settings', 'media');

        return Cache::remember($key, now()->addDay(), function () use ($type, $category) {
            $path = '/feed/'.$type->routePrefix().'.json'.($category ? '?category='.$category->slug : '');
            $items = array_map(fn (ContentItem $item) => $this->item($type, $item), $this->latest($type, $category));

            return $this->feed($path, '/'.$type->routePrefix(), $type->label().($category ? ': '.$category->name : ''), $items);
        });
    }

    /**
     * @return list<ContentItem>
     */
    private function latest(ContentType $type, ?Term $category): array
    {
        return $type->query()->published()
            ->with(array_filter(['featuredMedia', $type->taxonomy() ? 'terms' : null]))
            ->when($category, fn ($query) => $query->whereHas('terms', fn ($terms) => $terms->whereKey($category->id)))
            ->orderByDesc('published_at')->orderByDesc('id')
            ->limit(self::LIMIT)->get()->all();
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    private function feed(string $path, string $homePath, ?string $section, array $items): array
    {
        $site = $this->settings->group('site');
        $logo = $this->settings->get('navigation', 'logo_media_id');
        $icon = $logo ? Media::query()->find((int) $logo)?->url() : null;

        return array_filter([
            'version' => 'https://jsonfeed.org/version/1.1',
            'title' => $section ? "{$site['name']} — {$section}" : (string) $site['name'],
            'home_page_url' => $this->seo->absolute($homePath),
            'feed_url' => $this->seo->absolute($path),
            'description' => $site['description'] ?: null,
            'icon' => $icon ? $this->seo->absolute($icon) : null,
            'language' => str_replace('_', '-', app()->getLocale()),
            'items' => $items,
        ], fn ($value) => $value !== null && $value !== '');
    }

    /**
     * @return array<string, mixed>
     */
    private function item(ContentType $type, ContentItem $item): array
    {
        $image = $item->featuredMedia?->toImageArray();
        $body = $item->getAttribute('body');
        $tags = $type->taxonomy() === null ? [] : $item->terms->where('taxonomy', $type->taxonomy())->pluck('name')->values()->all();

        return array_filter([
            'id' => $type->key().':'.$item->getKey(),
            'url' => $this->seo->absolute($item->url()),
            'title' => (string) $item->getAttribute('title'),
            'summary' => HtmlSanitizer::toText($item->getAttribute('excerpt')),
            'content_html' => is_string($body) && $body !== '' ? ($this->html->sanitize($body) ?: null) : null,
            'image' => isset($image['src']) ? $this->seo->absolute((string) $image['src']) : null,
            'date_published' => $item->published_at?->toRfc3339String(),
            'date_modified' => $item->updated_at?->toRfc3339String(),
            'tags' => $tags ?: null,
            // Extension for PACMS sites reading this feed; other readers ignore it.
            '_pacms' => ['type' => $type->key(), 'slug' => (string) $item->getAttribute('slug')],
        ], fn ($value) => $value !== null);
    }
}
