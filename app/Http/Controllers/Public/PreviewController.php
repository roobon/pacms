<?php

namespace App\Http\Controllers\Public;

use App\Cms\Content\ContentTypeRegistry;
use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\Revision;
use App\Services\Content\ContentPayloadBuilder;
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

    /**
     * Preview of a content item's current state (news, events…; direct publishing has no
     * separate working copy, so this shows the saved item, published or not).
     */
    public function content(Request $request, string $type, int $id, ContentTypeRegistry $types, SitePayload $sitePayload, ContentPayloadBuilder $builder): Response
    {
        $definition = $types->forRoutePrefix($type) ?? abort(404);
        $model = $definition->modelClass();
        $item = $model::query()->findOrFail($id);
        Gate::authorize('view', $item);

        $site = $sitePayload->build();
        $payload = $builder->preview($item);

        return response()->view('spa', [
            'site' => $site,
            'seo' => ['robots' => 'noindex,nofollow', 'canonical' => null, 'json_ld' => []] + $payload['seo'],
            'initial' => [
                'site' => $site,
                'route' => ['path' => '/'.$request->path(), 'status' => 200, 'preview' => true],
                'content' => $payload,
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
