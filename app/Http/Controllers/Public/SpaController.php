<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Public\SitePayload;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Serves the public React SPA through the "server-assisted shell" (CMS-ARCHITECTURE.md §2.3):
 * correct HTTP status, server-rendered SEO/Open Graph tags and the initial data payload.
 *
 * Phase 2 knows only the fixed SPA routes below; CMS pages and content types are added to
 * the path resolver in later phases.
 */
class SpaController extends Controller
{
    /** Fixed SPA routes: path pattern => page title (null = site name only). */
    private const ROUTES = [
        '#^$#' => null,
        '#^account$#' => 'My account',
        '#^account/login$#' => 'Sign in',
        '#^account/register$#' => 'Create an account',
    ];

    /** Paths owned by the server; unknown URLs below them are plain 404s, not SPA pages. */
    private const RESERVED_PREFIXES = ['admin', 'api', 'auth', 'build', 'storage', 'sanctum'];

    public function __invoke(Request $request, SitePayload $sitePayload): Response
    {
        $path = trim($request->path(), '/');

        foreach (self::RESERVED_PREFIXES as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                abort(404);
            }
        }

        [$status, $pageTitle] = $this->resolve($path);
        $site = $sitePayload->build();

        $title = $pageTitle ? "{$pageTitle} · {$site['name']}" : trim($site['name'].($site['tagline'] ? ' — '.$site['tagline'] : ''));
        $canonical = $site['url'].($path === '' ? '/' : '/'.$path);

        return response()->view('spa', [
            'site' => $site,
            'seo' => [
                'title' => $title,
                'description' => $site['description'],
                'canonical' => $status === 200 ? $canonical : null,
                'robots' => $status === 200 && ! str_starts_with($path, 'account') ? 'index,follow' : 'noindex,follow',
                'og_type' => 'website',
                'json_ld' => $path === '' ? $this->organizationSchema($site) : null,
            ],
            'initial' => [
                'site' => $site,
                'route' => ['path' => '/'.$path, 'status' => $status],
            ],
        ], $status)->header('Cache-Control', 'no-cache, private');
    }

    /**
     * @return array{0: int, 1: ?string}
     */
    private function resolve(string $path): array
    {
        foreach (self::ROUTES as $pattern => $title) {
            if (preg_match($pattern, $path)) {
                return [200, $title];
            }
        }

        return [404, 'Page not found'];
    }

    /**
     * @param  array<string, mixed>  $site
     * @return array<string, mixed>
     */
    private function organizationSchema(array $site): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => $site['name'],
            'url' => $site['url'],
            'email' => $site['contact']['email'] ?: null,
            'telephone' => $site['contact']['phone'] ?: null,
            'description' => $site['description'] ?: null,
        ]);
    }
}
