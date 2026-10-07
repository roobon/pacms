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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
            'items' => self::filteredQuery($filters, $request->user()->id)->paginate(36)->withQueryString(),
            'filters' => $filters,
            'categories' => Term::query()->inTaxonomy('media_category')->orderBy('name')->get(['id', 'name']),
            'tags' => Term::query()->inTaxonomy('tag')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('media.upload'), 403);

        $request->validate([
            'files' => ['required', 'array', 'max:20'],
            'files.*' => ['required', 'file'],
            'private' => ['boolean'],
            'allow_duplicates' => ['boolean'],
        ]);

        $count = 0;
        $duplicates = [];
        foreach ($request->file('files') as $file) {
            // The same file is not stored twice unless the editor asks for it.
            $existing = $request->boolean('allow_duplicates') ? null : $this->media->findDuplicate($file);
            if ($existing !== null) {
                $duplicates[] = ['id' => $existing->id, 'name' => $file->getClientOriginalName(), 'existing' => $existing->original_name];

                continue;
            }
            $this->media->store($file, $request->user(), [], $request->boolean('private'));
            $count++;
        }

        return redirect()->route('admin.media.index')
            ->with('success', trans_choice(':count file uploaded.|:count files uploaded.', $count, ['count' => $count]))
            ->with('duplicates', $duplicates);
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
     * Inline preview for staff (images, PDF, video, audio), including private files, which
     * are never reachable under /storage (CMS-ARCHITECTURE.md §14).
     */
    public function file(Request $request, Media $media): StreamedResponse
    {
        abort_unless($request->user()->can('media.view'), 403);

        $inline = $media->kind !== MediaKind::Document || $media->extension === 'pdf';

        $headers = [
            'Content-Type' => $media->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=300',
        ];

        // Nothing in a previewed file may run scripts (e.g. an SVG opened directly). PDFs are
        // left to the browser's built-in viewer, which a sandbox would switch off.
        if ($media->extension !== 'pdf') {
            $headers['Content-Security-Policy'] = "default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'; media-src 'self'; sandbox";
        }

        return Storage::disk($media->disk)->response($media->path, $media->original_name, $headers, $inline ? 'inline' : 'attachment');
    }

    public function visibility(Request $request, Media $media): RedirectResponse
    {
        abort_unless($request->user()->can('media.update'), 403);

        $private = $request->boolean('private');
        $this->media->setVisibility($media, $private, $request->user());

        return back()->with('success', $private ? __('The file is now private: staff only.') : __('The file is now public.'));
    }

    /**
     * Bulk actions on selected items. Each item is handled on its own: items that cannot
     * be changed (e.g. a used file cannot be deleted or made private) are skipped and named.
     */
    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['integer'],
            'action' => ['required', Rule::in(['category', 'tag', 'private', 'public', 'delete'])],
            'term_id' => ['nullable', 'required_if:action,category,tag', 'integer'],
        ], ['ids.required' => __('Select at least one file.')]);

        $user = $request->user();
        abort_unless($user->can($data['action'] === 'delete' ? 'media.delete' : 'media.update'), 403);

        if (in_array($data['action'], ['category', 'tag'], true)) {
            $taxonomy = $data['action'] === 'category' ? 'media_category' : 'tag';
            abort_unless(Term::query()->inTaxonomy($taxonomy)->whereKey($data['term_id'])->exists(), 422);
        }

        $done = 0;
        $skipped = [];
        foreach (Media::query()->whereKey($data['ids'])->get() as $media) {
            try {
                match ($data['action']) {
                    'category', 'tag' => $media->terms()->syncWithoutDetaching([(int) $data['term_id']]),
                    'private' => $this->media->setVisibility($media, true, $user),
                    'public' => $this->media->setVisibility($media, false, $user),
                    default => $this->media->delete($media, $user), // 'delete' (validated above)
                };
                $done++;
            } catch (ValidationException $e) {
                $skipped[] = $media->original_name.': '.collect($e->errors())->flatten()->first();
            }
        }

        $redirect = redirect()->back()->with('success', trans_choice(':count file updated.|:count files updated.', $done, ['count' => $done]));

        return $skipped === [] ? $redirect : $redirect->with('skipped', $skipped);
    }

    public const SORTS = ['newest' => 'Newest first', 'oldest' => 'Oldest first', 'name' => 'Name (A–Z)', 'largest' => 'Largest first', 'smallest' => 'Smallest first'];

    /**
     * Library and picker filters (Phase 7): search, type, category, tag, visibility,
     * usage, missing alt text, own uploads and sort order.
     *
     * @return array<string, mixed>
     */
    public static function validateFilters(Request $request): array
    {
        return $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'kind' => ['nullable', Rule::enum(MediaKind::class)],
            'category' => ['nullable', 'integer'],
            'tag' => ['nullable', 'integer'],
            'visibility' => ['nullable', Rule::in(['public', 'private'])],
            'usage' => ['nullable', Rule::in(['used', 'unused'])],
            'needs_alt' => ['nullable', 'boolean'],
            'mine' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in(array_keys(self::SORTS))],
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Media>
     */
    public static function filteredQuery(array $filters, ?int $userId = null): Builder
    {
        $used = fn ($query) => $query->select(DB::raw(1))->from('content_references')
            ->whereColumn('content_references.target_id', 'media.id')
            ->where('content_references.target_type', 'media');

        $query = Media::query()
            ->when($filters['q'] ?? null, fn ($query, $q) => $query->where(fn ($query) => $query
                ->where('original_name', 'like', "%{$q}%")
                ->orWhere('alt', 'like', "%{$q}%")
                ->orWhere('caption', 'like', "%{$q}%")
                ->orWhere('description', 'like', "%{$q}%")
                ->orWhere('credit', 'like', "%{$q}%")
                ->orWhereHas('terms', fn ($terms) => $terms->where('name', 'like', "%{$q}%"))))
            ->when($filters['kind'] ?? null, fn ($query, $kind) => $query->where('kind', $kind))
            ->when($filters['category'] ?? null, fn ($query, $category) => $query->whereHas('terms', fn ($terms) => $terms->whereKey($category)))
            ->when($filters['tag'] ?? null, fn ($query, $tag) => $query->whereHas('terms', fn ($terms) => $terms->whereKey($tag)))
            ->when(($filters['visibility'] ?? null) === 'public', fn ($query) => $query->where('disk', config('pacms.media.disk')))
            ->when(($filters['visibility'] ?? null) === 'private', fn ($query) => $query->where('disk', '!=', config('pacms.media.disk')))
            ->when(($filters['usage'] ?? null) === 'used', fn ($query) => $query->whereExists($used))
            ->when(($filters['usage'] ?? null) === 'unused', fn ($query) => $query->whereNotExists($used))
            ->when(! empty($filters['needs_alt']), fn ($query) => $query
                ->where('kind', MediaKind::Image)
                ->where('is_decorative', false)
                ->where(fn ($q) => $q->whereNull('alt')->orWhere('alt', '')))
            ->when(! empty($filters['mine']) && $userId !== null, fn ($query) => $query->where('uploaded_by', $userId));

        return match ($filters['sort'] ?? 'newest') {
            'oldest' => $query->oldest('id'),
            'name' => $query->orderBy('original_name')->orderBy('id'),
            'largest' => $query->orderByDesc('size')->orderByDesc('id'),
            'smallest' => $query->orderBy('size')->orderBy('id'),
            default => $query->latest('id'),
        };
    }
}
