<?php

namespace App\Services\Public;

use App\Models\News;
use App\Models\Page;
use App\Models\Redirect;
use App\Services\Seo\RedirectService;
use App\Services\Settings\SettingsService;

/**
 * Maps a public URL to what should be shown there. Used by the SPA shell and by
 * GET /api/v1/resolve, so both always agree (CMS-ARCHITECTURE.md §2.3, §24.2).
 *
 * Order: live page → redirect → not found. A live page always wins over a stale redirect.
 */
class PathResolver
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly RedirectService $redirects,
    ) {}

    /**
     * @return array{kind: 'home'|'page'|'news'|'redirect'|'not_found', page?: Page, news?: News, redirect?: Redirect}
     */
    public function resolve(string $path): array
    {
        $path = trim(mb_strtolower(rawurldecode($path)), '/');

        if ($path === '') {
            $home = $this->homepage();

            return $home ? ['kind' => 'page', 'page' => $home] : ['kind' => 'home'];
        }

        $page = Page::query()->live()->where('published_path', $path)->first();
        if ($page !== null) {
            return ['kind' => 'page', 'page' => $page];
        }

        if (preg_match('#^news/([a-z0-9-]+)$#', $path, $match)) {
            $news = News::query()->published()->where('slug', $match[1])->first();
            if ($news !== null) {
                return ['kind' => 'news', 'news' => $news];
            }
        }

        $redirect = $this->redirects->find('/'.$path);
        if ($redirect !== null) {
            return ['kind' => 'redirect', 'redirect' => $redirect];
        }

        return ['kind' => 'not_found'];
    }

    public function homepage(): ?Page
    {
        $id = $this->settings->get('site', 'homepage_page_id');

        return $id ? Page::query()->live()->find($id) : null;
    }
}
