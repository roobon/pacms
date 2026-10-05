<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\Revision;
use App\Services\Pages\PageService;
use App\Services\Revisions\RevisionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PageRevisionController extends Controller
{
    public function index(Request $request, Page $page, RevisionService $revisions): View
    {
        Gate::authorize('viewRevisions', $page);

        $list = $page->revisions()->with('author:id,name')->paginate(25);

        $compare = null;
        if ($request->filled(['from', 'to'])) {
            $from = $this->find($page, $request->integer('from'));
            $to = $this->find($page, $request->integer('to'));
            $compare = [
                'from' => $from,
                'to' => $to,
                'changes' => $revisions->compare($from->snapshot, $to->snapshot),
            ];
        }

        return view('admin.pages.revisions', [
            'page' => $page,
            'revisions' => $list,
            'compare' => $compare,
            'canRestore' => Gate::allows('restoreRevision', $page),
        ]);
    }

    public function restore(Request $request, Page $page, Revision $revision, PageService $pages): RedirectResponse
    {
        Gate::authorize('restoreRevision', $page);

        $pages->restore($request->user(), $page, $revision);

        return redirect()->route('admin.pages.edit', $page)
            ->with('success', __('Revision #:number restored as a draft. Publish to make it live.', ['number' => $revision->number]));
    }

    private function find(Page $page, int $number): Revision
    {
        return Revision::query()
            ->where('revisionable_type', $page->getMorphClass())
            ->where('revisionable_id', $page->getKey())
            ->where('number', $number)
            ->firstOrFail();
    }
}
