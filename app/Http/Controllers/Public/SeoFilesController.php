<?php

namespace App\Http\Controllers\Public;

use App\Cms\Content\ContentTypeRegistry;
use App\Http\Controllers\Controller;
use App\Models\ContentItem;
use App\Models\Page;
use App\Services\Cache\CacheVersions;
use App\Services\Settings\SettingsService;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use XMLWriter;

/**
 * robots.txt and XML sitemaps (CMS-ARCHITECTURE.md §22). Only live content is listed.
 */
class SeoFilesController extends Controller
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly CacheVersions $versions,
        private readonly ContentTypeRegistry $types,
    ) {}

    public function robots(): Response
    {
        $lines = trim((string) $this->settings->get('seo', 'robots_txt'));
        $body = $lines."\n\nSitemap: ".url('/sitemap.xml')."\n";

        return response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'public, max-age=3600']);
    }

    public function index(): Response
    {
        $key = 'pacms:sitemap-index:'.$this->versions->fingerprint('pages', ...array_keys($this->types->all()));
        $xml = Cache::remember($key, now()->addDay(), function () {
            $writer = $this->writer();
            $writer->startElement('sitemapindex');
            $writer->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
            $this->sitemapEntry($writer, '/sitemaps/pages.xml', Page::query()->live()->max('published_at'));

            // One sitemap per content module that has published items.
            foreach ($this->types->all() as $type) {
                $model = $type->modelClass();
                $lastmod = $model::query()->published()->max('updated_at');
                if ($lastmod !== null) {
                    $this->sitemapEntry($writer, '/sitemaps/'.$type->routePrefix().'.xml', $lastmod);
                }
            }
            $writer->endElement();

            return $writer->outputMemory();
        });

        return $this->xml($xml);
    }

    public function pages(): Response
    {
        $xml = Cache::remember('pacms:sitemap-pages:'.$this->versions->fingerprint('pages', 'settings'), now()->addDay(), function () {
            $homeId = (int) $this->settings->get('site', 'homepage_page_id');
            $writer = $this->writer();
            $writer->startElement('urlset');
            $writer->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

            $writer->startElement('url');
            $writer->writeElement('loc', rtrim(url('/'), '/').'/');
            $writer->endElement();

            Page::query()->live()->with('publishedRevision')->orderBy('published_path')->each(function (Page $page) use ($writer, $homeId) {
                if ($page->getKey() === $homeId || (($page->publishedRevision?->snapshot['seo']['robots_index'] ?? true) === false)) {
                    return;
                }
                $writer->startElement('url');
                $writer->writeElement('loc', url('/'.$page->published_path));
                $writer->writeElement('lastmod', (string) $page->published_at?->toAtomString());
                $writer->endElement();
            });

            $writer->endElement();

            return $writer->outputMemory();
        });

        return $this->xml($xml);
    }

    /**
     * Published items of one content module, plus its archive page. Items set to
     * "hide from search engines" are left out.
     */
    public function module(string $module): Response
    {
        $type = $this->types->forRoutePrefix($module) ?? abort(404);

        $key = 'pacms:sitemap-module:'.$type->key().':'.$this->versions->fingerprint($type->key(), 'settings');
        $xml = Cache::remember($key, now()->addDay(), function () use ($type) {
            $writer = $this->writer();
            $writer->startElement('urlset');
            $writer->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

            $writer->startElement('url');
            $writer->writeElement('loc', url('/'.$type->routePrefix()));
            $writer->endElement();

            $model = $type->modelClass();
            $model::query()->published()->with('seo')->orderByDesc('published_at')->each(function (ContentItem $item) use ($writer) {
                if ($item->seo !== null && $item->seo->robots_index === false) {
                    return;
                }
                $writer->startElement('url');
                $writer->writeElement('loc', url($item->url()));
                $writer->writeElement('lastmod', (string) ($item->updated_at ?? $item->published_at)?->toAtomString());
                $writer->endElement();
            });

            $writer->endElement();

            return $writer->outputMemory();
        });

        return $this->xml($xml);
    }

    private function sitemapEntry(XMLWriter $writer, string $path, mixed $lastmod): void
    {
        $writer->startElement('sitemap');
        $writer->writeElement('loc', url($path));
        if ($lastmod) {
            $writer->writeElement('lastmod', Carbon::parse($lastmod)->toAtomString());
        }
        $writer->endElement();
    }

    private function writer(): XMLWriter
    {
        $writer = new XMLWriter;
        $writer->openMemory();
        $writer->startDocument('1.0', 'UTF-8');

        return $writer;
    }

    private function xml(string $body): Response
    {
        return response($body, 200, ['Content-Type' => 'application/xml; charset=UTF-8', 'Cache-Control' => 'public, max-age=3600']);
    }
}
