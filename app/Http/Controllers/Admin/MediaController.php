<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MediaKind;
use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\Term;
use App\Services\Content\ContentReferenceService;
use App\Services\Media\MediaService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Media Library screens. Upload goes through MediaService (UploadGuard, re-encoding).
 */
class MediaController extends Controller
{
    public function __construct(private readonly MediaService $media) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('media.view'), 403);

        $filters = self::validateFilters($request);

        return view('admin.media.index', [
            'items' => self::filteredQuery($filters)->paginate(36)->withQueryString(),
            'filters' => $filters,
            'categories' => Term::query()->inTaxonomy('media_category')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('media.upload'), 403);

        $request->validate([
            'files' => ['required', 'array', 'max:20'],
            'files.*' => ['required', 'file'],
            'private' => ['boolean'],
        ]);

        $count = 0;
        foreach ($request->file('files') as $file) {
            $this->media->store($file, $request->user(), [], $request->boolean('private'));
            $count++;
        }

        return redirect()->route('admin.media.index')->with('success', trans_choice(':count file uploaded.|:count files uploaded.', $count, ['count' => $count]));
    }

    public function edit(Request $request, Media $media, ContentReferenceService $references): View
    {
        abort_unless($request->user()->can('media.view'), 403);

        return view('admin.media.edit', [
            'item' => $media->load(['terms', 'uploader:id,name']),
            'usages' => $references->usagesOf($media),
            'categories' => Term::query()->inTaxonomy('media_category')->orderBy('name')->get(['id', 'name']),
            'tags' => Term::query()->inTaxonomy('tag')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Media $media): RedirectResponse
    {
        abort_unless($request->user()->can('media.update'), 403);

        $data = $request->validate([
            'alt' => ['nullable', 'string', 'max:255'],
            'caption' => ['nullable', 'string', 'max:1000'],
            'description' => ['nullable', 'string', 'max:2000'],
            'credit' => ['nullable', 'string', 'max:255'],
            'is_decorative' => ['boolean'],
            'focal_x' => ['nullable', 'numeric', 'between:0,1'],
            'focal_y' => ['nullable', 'numeric', 'between:0,1'],
            'categories' => ['array'],
            'categories.*' => ['integer'],
            'tags' => ['array'],
            'tags.*' => ['integer'],
        ]);

        if ($media->isImage() && ! $request->boolean('is_decorative') && blank($data['alt'] ?? null)) {
            return back()->withInput()->withErrors(['alt' => __('Describe the image for people who cannot see it, or mark it as decorative.')]);
        }

        $this->media->updateMetadata($media, [
            'alt' => $data['alt'] ?? null,
            'caption' => $data['caption'] ?? null,
            'description' => $data['description'] ?? null,
            'credit' => $data['credit'] ?? null,
            'is_decorative' => $request->boolean('is_decorative'),
            'focal_point' => isset($data['focal_x'], $data['focal_y']) ? ['x' => (float) $data['focal_x'], 'y' => (float) $data['focal_y']] : null,
        ], $request->user());

        $media->syncTerms('media_category', $data['categories'] ?? []);
        $media->syncTerms('tag', $data['tags'] ?? []);

        return redirect()->route('admin.media.edit', $media)->with('success', __('Details saved.'));
    }

    public function replace(Request $request, Media $media): RedirectResponse
    {
        abort_unless($request->user()->can('media.update'), 403);

        $request->validate(['file' => ['required', 'file']]);
        $this->media->replace($media, $request->file('file'), $request->user());

        return redirect()->route('admin.media.edit', $media)->with('success', __('File replaced. Everywhere it is used now shows the new file.'));
    }

    public function destroy(Request $request, Media $media): RedirectResponse
    {
        abort_unless($request->user()->can('media.delete'), 403);

        $this->media->delete($media, $request->user(), $request->boolean('force'));

        return redirect()->route('admin.media.index')->with('success', __('":name" deleted.', ['name' => $media->original_name]));
    }

    /**
     * Private files are only ever served through this authorised download.
     */
    public function download(Request $request, Media $media): StreamedResponse
    {
        abort_unless($request->user()->can('media.view'), 403);

        return Storage::disk($media->disk)->download($media->path, $media->original_name, [
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * @return array{q?: ?string, kind?: ?string, category?: ?int}
     */
    public static function validateFilters(Request $request): array
    {
        return $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'kind' => ['nullable', Rule::enum(MediaKind::class)],
            'category' => ['nullable', 'integer'],
        ]);
    }

    /**
     * @param  array{q?: ?string, kind?: ?string, category?: ?int}  $filters
     * @return Builder<Media>
     */
    public static function filteredQuery(array $filters): Builder
    {
        return Media::query()
            ->when($filters['q'] ?? null, fn ($query, $q) => $query->where(
                fn ($query) => $query->where('original_name', 'like', "%{$q}%")->orWhere('alt', 'like', "%{$q}%")->orWhere('caption', 'like', "%{$q}%")
            ))
            ->when($filters['kind'] ?? null, fn ($query, $kind) => $query->where('kind', $kind))
            ->when($filters['category'] ?? null, fn ($query, $category) => $query->whereHas('terms', fn ($terms) => $terms->whereKey($category)))
            ->latest('id');
    }
}
