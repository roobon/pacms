<?php

namespace App\Services\News;

use App\Models\News;
use App\Services\Seo\SeoResolver;
use App\Services\Settings\SettingsService;
use App\Support\Html\HtmlSanitizer;

/**
 * Public payload for a published news item (minimal; the full News module is Phase 8).
 */
class NewsPayloadBuilder
{
    public function __construct(
        private readonly SeoResolver $seo,
        private readonly SettingsService $settings,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(News $news): array
    {
        $news->loadMissing(['featuredMedia', 'terms']);
        $url = $this->seo->absolute($news->url());
        $category = $news->terms->firstWhere('taxonomy', 'news_category');
        $breadcrumbs = [
            ['title' => (string) $this->settings->get('site', 'name'), 'url' => '/'],
            ['title' => $news->title, 'url' => $news->url()],
        ];

        $seo = $this->seo->resolve([
            'title' => $news->title,
            'excerpt' => HtmlSanitizer::toText($news->excerpt),
            'url' => $url,
            'image' => $news->featuredMedia,
            'type' => 'article',
        ], null, $breadcrumbs);

        $seo['json_ld'][] = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'NewsArticle',
            'headline' => $news->title,
            'datePublished' => $news->published_at?->toIso8601String(),
            'dateModified' => $news->updated_at?->toIso8601String(),
            'image' => $seo['og']['image'] ?? null,
            'mainEntityOfPage' => $url,
            'publisher' => ['@type' => 'Organization', 'name' => (string) $this->settings->get('site', 'name')],
        ]);

        return [
            'type' => 'news',
            'id' => $news->id,
            'title' => $news->title,
            'path' => $news->url(),
            'url' => $url,
            'excerpt' => HtmlSanitizer::toText($news->excerpt),
            'excerpt_html' => HtmlSanitizer::toText($news->excerpt) === null ? null : app(HtmlSanitizer::class)->inline((string) $news->excerpt),
            'featured_image' => $news->featuredMedia?->toImageArray('(min-width: 1320px) 1280px, 100vw'),
            'category' => $category?->name,
            'published_at' => $news->published_at?->toIso8601String(),
            'breadcrumbs' => $breadcrumbs,
            'seo' => $seo,
        ];
    }
}
