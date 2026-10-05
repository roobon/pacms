<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;

/**
 * Issues a short-lived signed preview link (CMS-ARCHITECTURE.md §4.4). The preview
 * page additionally requires a signed-in user who may view the page.
 */
class PagePreviewController extends Controller
{
    public function __invoke(Request $request, Page $page): RedirectResponse
    {
        Gate::authorize('view', $page);

        $revision = $request->integer('revision') ?: null;

        $url = URL::temporarySignedRoute(
            'preview.page',
            now()->addMinutes((int) config('pacms.preview.ttl_minutes')),
            array_filter(['page' => $page->getKey(), 'revision' => $revision]),
            absolute: false,
        );

        return redirect($url);
    }
}
