<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Redirect;
use App\Services\News\NewsPayloadBuilder;
use App\Services\Pages\PagePayloadBuilder;
use App\Services\Public\PathResolver;
use App\Services\Public\SitePayload;
use App\Services\Seo\RedirectService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Serves the public React SPA through the "server-assisted shell" (CMS-ARCHITECTURE.md §2.3):
 * correct HTTP status, redirects, server-rendered SEO/Open Graph tags and the initial data
 * payload, so the first paint needs no API request and crawlers see real metadata.
 */
class SpaController extends Controller
{
    /** Fixed SPA routes: path pattern => page title. */
    private const APP_ROUTES = [
        '#^account$#' => 'My account',
        '#^account/login$#' => 'Sign in',
        '#^account/register$#' => 'Create an account',
    ];

    /** Paths owned by the server; unknown URLs below them are plain 404s, not SPA pages. */
    private const RESERVED_PREFIXES = ['admin', 'api', 'auth', 'build', 'storage', 'sanctum', 'preview'];

    public function __invoke(
        Request $request,
        SitePayload $sitePayload,
        PathResolver $resolver,
        PagePayloadBuilder $pages,
        RedirectService $redirects,
        NewsPayloadBuilder $newsPayload,
    ): Response|RedirectResponse {
        $path = trim($request->path(), '/');

        foreach (self::RESERVED_PREFIXES as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                abort(404);
            }
        }

        $site = $sitePayload->build();

        foreach (self::APP_ROUTES as $pattern => $title) {
            if (preg_match($pattern, $path)) {
                return $this->shell($site, $path, 200, $this->basicSeo($site, $title, null, noindex: true));
            }
        }

        $resolved = $resolver->resolve($path);

        return match ($resolved['kind']) {
            'page' => $this->pageResponse($site, $path, $pages->forLivePage($resolved['page'])),
            'news' => $this->shell($site, $path, 200, ($news = $newsPayload->build($resolved['news']))['seo'], ['news' => $news]),
            'home' => $this->shell($site, $path, 200, $this->basicSeo($site, null, $site['url'].'/', organization: true)),
            'redirect' => $this->redirectResponse($resolved['redirect'], $redirects),
            default => $this->shell($site, $path, 404, $this->basicSeo($site, 'Page not found', null, noindex: true)),
        };
    }

    /**
     * @param  array<string, mixed>  $site
     * @param  array<string, mixed>  $page
     */
    private function pageResponse(array $site, string $path, array $page): Response|RedirectResponse
    {
        // The homepage page is served at "/" only; its own path redirects there.
        if ($page['is_home'] && $path !== '') {
            return redirect('/', 301);
        }

        $seo = $page['seo'];
        $seo['json_ld'] = array_merge(
            $page['is_home'] ? [$this->organizationSchema($site)] : [],
            $seo['json_ld'] ?? [],
        );

        return $this->shell($site, $path, 200, $seo, ['page' => $page]);
    }

    private function redirectResponse(Redirect $redirect, RedirectService $redirects): RedirectResponse
    {
        $redirects->recordHit($redirect);

        return redirect($redirect->target_path, $redirect->status_code);
    }

    /**
     * @param  array<string, mixed>  $site
     * @param  array<string, mixed>  $seo
     * @param  array<string, mixed>  $extra
     */
    private function shell(array $site, string $path, int $status, array $seo, array $extra = []): Response
    {
        return response()->view('spa', [
            'site' => $site,
            'seo' => $seo,
            'initial' => [
                'site' => $site,
                'route' => ['path' => '/'.$path, 'status' => $status],
            ] + $extra,
        ], $status)->header('Cache-Control', 'no-cache, private');
    }

    /**
     * @param  array<string, mixed>  $site
     * @return array<string, mixed>
     */
    private function basicSeo(array $site, ?string $title, ?string $canonical, bool $noindex = false, bool $organization = false): array
    {
        return [
            'title' => $title ? "{$title} · {$site['name']}" : trim($site['name'].($site['tagline'] ? ' — '.$site['tagline'] : '')),
            'description' => $site['description'] ?: null,
            'canonical' => $canonical,
            'robots' => $noindex ? 'noindex,follow' : 'index,follow',
            'og' => ['type' => 'website', 'title' => $title ?? $site['name'], 'description' => $site['description'] ?: null, 'image' => null, 'image_alt' => null],
            'twitter' => ['card' => 'summary'],
            'json_ld' => $organization ? [$this->organizationSchema($site)] : [],
        ];
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
