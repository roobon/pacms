<?php

namespace App\Services\Pages;

use App\Cms\Blocks\BlockPayloadResolver;
use App\Cms\Content\ContentTypeRegistry;
use App\Models\Media;
use App\Models\Page;
use App\Models\Revision;
use App\Services\Cache\CacheVersions;
use App\Services\Seo\SeoResolver;
use App\Services\Settings\SettingsService;
use App\Support\Html\HtmlSanitizer;
use Illuminate\Support\Facades\Cache;

/**
 * The public page payload (API-ARCHITECTURE.md §2.2), built from a page's published
 * snapshot — never from the working copy — or, for previews, from any snapshot.
 */
class PagePayloadBuilder
{
    public function __construct(
        private readonly SeoResolver $seo,
        private readonly CacheVersions $versions,
        private readonly SettingsService $settings,
        private readonly BlockPayloadResolver $blocks,
    ) {}

    /**
     * Payload for a live page (cached until pages, media or settings change).
     *
     * @return array<string, mixed>
     */
    public function forLivePage(Page $page): array
    {
        // The path is hashed too: nested page paths can be long.
        $key = 'pacms:page-payload:'.$page->published_revision_id.':'.sha1((string) $page->published_path).':'
            .$this->versions->fingerprint('pages', 'media', 'settings', 'globals', 'block_types', 'testimonials', ...array_keys(app(ContentTypeRegistry::class)->all()));

        return Cache::remember($key, now()->addDay(), function () use ($page) {
            /** @var Revision $revision */
            $revision = Revision::query()->findOrFail($page->published_revision_id);

            return $this->build($page, $revision->snapshot, (string) $page->published_path, $revision->created_at?->toIso8601String());
        });
    }

    /**
     * Payload for a preview (working copy or a specific revision). Never cached.
     *
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    public function forPreview(Page $page, array $snapshot): array
    {
        return $this->build($page, $snapshot, (string) $page->path, now()->toIso8601String()) + ['preview' => true];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function build(Page $page, array $snapshot, string $path, ?string $updatedAt): array
    {
        $fields = (array) ($snapshot['fields'] ?? []);
        $isHome = (int) $this->settings->get('site', 'homepage_page_id') === (int) $page->getKey();
        $urlPath = $isHome ? '/' : '/'.$path;
        $url = $this->seo->absolute($urlPath);

        $featured = isset($fields['featured_media_id']) ? Media::query()->find($fields['featured_media_id']) : null;
        $breadcrumbs = $this->breadcrumbs($page, (string) ($fields['title'] ?? $page->title), $urlPath, $isHome);

        return [
            'type' => 'page',
            'id' => $page->getKey(),
            'title' => (string) ($fields['title'] ?? $page->title),
            'path' => $urlPath,
            'url' => $url,
            'is_home' => $isHome,
            // Plain text for cards, search and sharing; the formatted version for the page lead.
            'excerpt' => HtmlSanitizer::toText($fields['excerpt'] ?? null),
            'excerpt_html' => $this->summaryHtml($fields['excerpt'] ?? null),
            'featured_image' => $featured?->toImageArray('(min-width: 1320px) 1280px, 100vw'),
            'template' => (string) ($fields['template'] ?? 'default'),
            'show_title' => (bool) ($fields['show_title'] ?? true),
            'published_at' => $page->first_published_at?->toIso8601String(),
            'updated_at' => $updatedAt,
            'breadcrumbs' => $breadcrumbs,
            'seo' => $this->seo->resolve([
                'title' => (string) ($fields['title'] ?? $page->title),
                'excerpt' => HtmlSanitizer::toText($fields['excerpt'] ?? null),
                'url' => $url,
                'is_home' => $isHome,
                'image' => $featured,
            ], $snapshot['seo'] ?? null, $breadcrumbs),
            'blocks' => $this->blocks->resolve((array) ($snapshot['blocks'] ?? []), includeHidden: false),
        ];
    }

    /**
     * Live ancestors + this page.
     *
     * @return list<array{title: string, url: string}>
     */
    private function breadcrumbs(Page $page, string $title, string $urlPath, bool $isHome): array
    {
        if ($isHome) {
            return [];
        }

        $crumbs = [['title' => $title, 'url' => $urlPath]];
        $parentId = $page->parent_id;
        $guard = 0;

        while ($parentId !== null && $guard++ < 10) {
            $parent = Page::query()->live()->with('publishedRevision')->find($parentId);
            if ($parent === null) {
                break;
            }
            array_unshift($crumbs, ['title' => $parent->publishedRevision?->snapshot['fields']['title'] ?? $parent->title, 'url' => '/'.$parent->published_path]);
            $parentId = $parent->parent_id;
        }

        array_unshift($crumbs, ['title' => (string) $this->settings->get('site', 'name'), 'url' => '/']);

        return $crumbs;
    }

    /**
     * Summaries are cleaned on save; cleaning again on output also covers older revisions.
     */
    private function summaryHtml(?string $excerpt): ?string
    {
        return HtmlSanitizer::toText($excerpt) === null ? null : app(HtmlSanitizer::class)->inline((string) $excerpt);
    }
}
