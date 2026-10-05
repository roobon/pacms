<?php

namespace App\Services\Seo;

use App\Models\Media;
use App\Services\Settings\SettingsService;

/**
 * Builds the SEO head for public content with generated defaults (CMS-ARCHITECTURE.md §22):
 * the editor's explicit values win, otherwise title/excerpt/featured image are used.
 */
class SeoResolver
{
    public function __construct(private readonly SettingsService $settings) {}

    /**
     * @param  array{title: string, excerpt?: ?string, url: string, is_home?: bool, image?: ?Media, type?: string}  $content
     * @param  array<string, mixed>|null  $seo  values from seo_metadata (snapshot)
     * @param  list<array{title: string, url: string}>  $breadcrumbs
     * @return array<string, mixed>
     */
    public function resolve(array $content, ?array $seo, array $breadcrumbs = []): array
    {
        $site = $this->settings->group('site');
        $seo = (array) $seo;
        $isHome = (bool) ($content['is_home'] ?? false);

        $defaultTitle = $isHome
            ? trim($site['name'].($site['tagline'] ? ' — '.$site['tagline'] : ''))
            : $content['title'].' · '.$site['name'];

        $title = filled($seo['title'] ?? null) ? (string) $seo['title'] : $defaultTitle;
        $description = $this->firstFilled($seo['description'] ?? null, $content['excerpt'] ?? null, $site['description']);
        $canonical = filled($seo['canonical_url'] ?? null) ? (string) $seo['canonical_url'] : $content['url'];

        $ogImageMedia = isset($seo['og_image_media_id']) ? Media::query()->find($seo['og_image_media_id']) : null;
        $image = $ogImageMedia ?? ($content['image'] ?? null);
        $imageUrl = $image?->isImage() && $image->isPublic() ? $this->absolute((string) $image->url()) : null;

        $index = (bool) ($seo['robots_index'] ?? true);
        $follow = (bool) ($seo['robots_follow'] ?? true);

        return [
            'title' => $title,
            'description' => $description !== null ? mb_substr(strip_tags($description), 0, 300) : null,
            'canonical' => $canonical,
            'robots' => ($index ? 'index' : 'noindex').','.($follow ? 'follow' : 'nofollow'),
            'og' => [
                'type' => $content['type'] ?? 'website',
                'title' => $this->firstFilled($seo['og_title'] ?? null, $title),
                'description' => $this->firstFilled($seo['og_description'] ?? null, $description),
                'image' => $imageUrl,
                'image_alt' => $image?->alt,
            ],
            'twitter' => ['card' => $imageUrl ? 'summary_large_image' : 'summary'],
            'json_ld' => $this->structuredData($content, $title, $description, $breadcrumbs, $site['name']),
        ];
    }

    public function absolute(string $path): string
    {
        return str_starts_with($path, 'http') ? $path : rtrim((string) config('app.url'), '/').'/'.ltrim($path, '/');
    }

    /**
     * @param  array<string, mixed>  $content
     * @param  list<array{title: string, url: string}>  $breadcrumbs
     * @return list<array<string, mixed>>
     */
    private function structuredData(array $content, string $title, ?string $description, array $breadcrumbs, string $siteName): array
    {
        $data = [[
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'name' => $title,
            'url' => $content['url'],
            'description' => $description,
            'isPartOf' => ['@type' => 'WebSite', 'name' => $siteName, 'url' => $this->absolute('/')],
        ]];

        if (count($breadcrumbs) > 1) {
            $data[] = [
                '@context' => 'https://schema.org',
                '@type' => 'BreadcrumbList',
                'itemListElement' => array_map(fn ($crumb, $i) => [
                    '@type' => 'ListItem',
                    'position' => $i + 1,
                    'name' => $crumb['title'],
                    'item' => $this->absolute($crumb['url']),
                ], $breadcrumbs, array_keys($breadcrumbs)),
            ];
        }

        return array_map(fn ($item) => array_filter($item, fn ($value) => $value !== null), $data);
    }

    private function firstFilled(mixed ...$values): ?string
    {
        foreach ($values as $value) {
            if (filled($value)) {
                return (string) $value;
            }
        }

        return null;
    }
}
