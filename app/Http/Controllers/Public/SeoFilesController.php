<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
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
    ) {}

    public function robots(): Response
    {
        $lines = trim((string) $this->settings->get('seo', 'robots_txt'));
        $body = $lines."\n\nSitemap: ".url('/sitemap.xml')."\n";

        return response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'public, max-age=3600']);
    }

    public function index(): Response
    {
        $xml = Cache::remember('pacms:sitemap-index:'.$this->versions->fingerprint('pages'), now()->addDay(), function () {
            $writer = $this->writer();
            $writer->startElement('sitemapindex');
            $writer->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
            $writer->startElement('sitemap');
            $writer->writeElement('loc', url('/sitemaps/pages.xml'));
            $lastmod = Page::query()->live()->max('published_at');
            if ($lastmod) {
                $writer->writeElement('lastmod', Carbon::parse($lastmod)->toAtomString());
            }
            $writer->endElement();
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
