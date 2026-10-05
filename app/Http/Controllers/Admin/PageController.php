<?php

namespace App\Http\Controllers\Admin;

use App\Cms\Blocks\BlockTreeRepository;
use App\Enums\ContentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PageRequest;
use App\Models\Page;
use App\Services\Pages\PageService;
use App\Services\Publishing\PublishingService;
use App\Services\Settings\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PageController extends Controller
{
    public function __construct(private readonly PageService $pages) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Page::class);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(ContentStatus::class)],
        ]);

        $pages = Page::query()
            ->with(['author:id,name'])
            ->when($filters['q'] ?? null, fn ($query, $q) => $query->where(
                fn ($query) => $query->where('title', 'like', "%{$q}%")->orWhere('path', 'like', "%{$q}%")
            ))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderBy('path')
            ->paginate(50)
            ->withQueryString();

        return view('admin.pages.index', [
            'pages' => $pages,
            'filters' => $filters,
            'homepageId' => (int) app(SettingsService::class)->get('site', 'homepage_page_id'),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Page::class);

        return view('admin.pages.form', $this->formData(new Page([
            'parent_id' => $request->integer('parent') ?: null,
        ]), $request));
    }

    public function store(PageRequest $request): RedirectResponse
    {
        Gate::authorize('create', Page::class);

        $page = $this->pages->create($request->user(), $request->pageData());

        return redirect()->route('admin.pages.edit', $page)->with('success', __('Page created as a draft.'));
    }

    public function edit(Request $request, Page $page): View
    {
        Gate::authorize('view', $page);

        return view('admin.pages.form', $this->formData($page->load(['seo', 'featuredMedia', 'author:id,name']), $request));
    }

    public function update(PageRequest $request, Page $page): RedirectResponse
    {
        Gate::authorize('update', $page);

        $this->pages->update($request->user(), $page, $request->pageData(), (int) $request->validated('lock_version'));

        return redirect()->route('admin.pages.edit', $page)->with('success', __('Changes saved.'));
    }

    public function destroy(Request $request, Page $page): RedirectResponse
    {
        Gate::authorize('delete', $page);

        $this->pages->delete($request->user(), $page);

        return redirect()->route('admin.pages.index')->with('success', __('Page ":title" deleted.', ['title' => $page->title]));
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Page $page, Request $request): array
    {
        $parents = Page::query()
            ->when($page->exists, fn ($query) => $query
                ->whereKeyNot($page->getKey())
                ->where('path', 'not like', $page->path.'/%'))
            ->where('status', '!=', ContentStatus::Archived)
            ->orderBy('path')
            ->get(['id', 'title', 'path']);

        return [
            'page' => $page,
            'parents' => $parents,
            'templates' => config('pacms.pages.templates'),
            'canEdit' => $page->exists ? Gate::allows('update', $page) : true,
            'actions' => $page->exists ? app(PublishingService::class)->availableActions($page, $request->user()) : [],
            'revisions' => $page->exists ? $page->revisions()->with('author:id,name')->limit(10)->get() : collect(),
            'blocks' => $page->exists ? app(BlockTreeRepository::class)->load($page) : [],
        ];
    }
}
