<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\Revision;
use App\Services\Pages\PagePayloadBuilder;
use App\Services\Public\SitePayload;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Secure preview of unpublished content (CMS-ARCHITECTURE.md §4.4): requires a valid,
 * unexpired signature AND a signed-in user allowed to view the page. Never cached,
 * never indexed.
 */
class PreviewController extends Controller
{
    /**
     * Empty SPA shell for the builder's live-preview iframe. Content arrives via postMessage.
     */
    public function builder(SitePayload $sitePayload): Response
    {
        $site = $sitePayload->build();

        return response()->view('spa', [
            'site' => $site,
            'seo' => ['title' => 'Builder preview', 'robots' => 'noindex,nofollow', 'json_ld' => []],
            'initial' => [
                'site' => $site,
                'route' => ['path' => '/__builder-preview', 'status' => 200, 'builder' => true],
            ],
        ])->withHeaders([
            'Cache-Control' => 'no-store, private',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    public function page(Request $request, Page $page, SitePayload $sitePayload, PagePayloadBuilder $builder): Response
    {
        Gate::authorize('view', $page);

        $snapshot = $page->load('seo')->toSnapshot();

        if ($request->filled('revision')) {
            $snapshot = Revision::query()
                ->where('revisionable_type', $page->getMorphClass())
                ->where('revisionable_id', $page->getKey())
                ->where('number', $request->integer('revision'))
                ->firstOrFail()
                ->snapshot;
        }

        $site = $sitePayload->build();
        $payload = $builder->forPreview($page, $snapshot);

        return response()->view('spa', [
            'site' => $site,
            'seo' => ['robots' => 'noindex,nofollow', 'canonical' => null, 'json_ld' => []] + $payload['seo'],
            'initial' => [
                'site' => $site,
                'route' => ['path' => '/'.$request->path(), 'status' => 200, 'preview' => true],
                'page' => $payload,
            ],
        ])->withHeaders([
            'Cache-Control' => 'no-store, private',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }
}
