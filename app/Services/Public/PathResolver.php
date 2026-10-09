<?php

namespace App\Services\Public;

use App\Cms\Content\ContentType;
use App\Cms\Content\ContentTypeRegistry;
use App\Models\ContentItem;
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
        private readonly ContentTypeRegistry $types,
    ) {}

    /**
     * @return array{kind: 'home'|'page'|'content'|'archive'|'redirect'|'not_found', page?: Page, type?: ContentType, item?: ContentItem, redirect?: Redirect}
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

        // Content modules: /{prefix} is the archive, /{prefix}/{slug} a published item.
        if (preg_match('#^([a-z0-9-]+)(?:/([a-z0-9-]+))?$#', $path, $match) && ($type = $this->types->forRoutePrefix($match[1])) !== null) {
            $slug = $match[2] ?? null;
            // Types without a listing page (an admin choice) have no archive URL.
            if ($slug === null && $type->hasArchive()) {
                return ['kind' => 'archive', 'type' => $type];
            }
            // Modules without pages of their own (partners) only have the listing.
            $item = $slug !== null && $type->hasDetailPages() ? $type->query()->published()->where('slug', $slug)->first() : null;
            if ($item !== null) {
                return ['kind' => 'content', 'type' => $type, 'item' => $item];
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
