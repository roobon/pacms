<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MediaKind;
use App\Http\Controllers\Controller;
use App\Models\News;
use App\Models\Term;
use App\Services\News\NewsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Minimal News admin (Phase 4): list, create, edit, publish/unpublish, delete.
 */
class NewsController extends Controller
{
    public function __construct(private readonly NewsService $news) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', News::class);

        $q = (string) ($request->validate(['q' => ['nullable', 'string', 'max:100']])['q'] ?? '');

        return view('admin.news.index', [
            'items' => News::query()
                ->with('author:id,name')
                ->when($q !== '', fn ($query) => $query->where('title', 'like', "%{$q}%"))
                ->latest('id')
                ->paginate(30)
                ->withQueryString(),
            'q' => $q,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', News::class);

        return view('admin.news.form', ['item' => new News, 'categories' => $this->categories()]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', News::class);

        $item = $this->news->create($request->user(), $this->validated($request));

        return redirect()->route('admin.news.edit', $item)->with('success', __('News item created as a draft.'));
    }

    public function edit(News $news): View
    {
        Gate::authorize('view', $news);

        return view('admin.news.form', ['item' => $news->load(['featuredMedia', 'terms']), 'categories' => $this->categories()]);
    }

    public function update(Request $request, News $news): RedirectResponse
    {
        Gate::authorize('update', $news);

        $this->news->update($request->user(), $news, $this->validated($request, $news));

        return redirect()->route('admin.news.edit', $news)->with('success', __('Changes saved.'));
    }

    public function publish(Request $request, News $news): RedirectResponse
    {
        Gate::authorize('view', $news);

        $request->boolean('unpublish')
            ? $this->news->unpublish($request->user(), $news)
            : $this->news->publish($request->user(), $news);

        return redirect()->route('admin.news.edit', $news)->with('success', $request->boolean('unpublish') ? __('Unpublished.') : __('Published.'));
    }

    public function destroy(Request $request, News $news): RedirectResponse
    {
        Gate::authorize('delete', $news);

        $this->news->delete($request->user(), $news);

        return redirect()->route('admin.news.index')->with('success', __('News item deleted.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?News $news = null): array
    {
        $request->merge(['featured' => $request->boolean('featured')]);

        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:191', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'excerpt' => ['nullable', 'string', 'max:1000'],
            'featured_media_id' => ['nullable', 'integer', Rule::exists('media', 'id')->where('kind', MediaKind::Image->value)],
            'featured' => ['boolean'],
            'categories' => ['array'],
            'categories.*' => ['integer', Rule::exists('terms', 'id')->where('taxonomy', 'news_category')],
        ]);
    }

    /**
     * @return Collection<int, Term>
     */
    private function categories(): Collection
    {
        return Term::query()->inTaxonomy('news_category')->orderBy('name')->get(['id', 'name']);
    }
}
