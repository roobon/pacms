@php
    $svgAllowed = app(\App\Services\Media\MediaService::class)->mayUploadSvg(auth()->user());
    $extensions = collect(array_keys(config('pacms.media.allowed')))->when($svgAllowed, fn ($c) => $c->push('svg'))->map(fn ($e) => '.'.$e)->implode(',');
    $canBulk = auth()->user()->can('media.update') || auth()->user()->can('media.delete');
    $activeFilters = collect($filters)->except('sort')->filter()->isNotEmpty();
@endphp
<x-admin.layout title="Media Library">
    <x-admin.page-header title="Media Library" subtitle="Images, documents, video and audio. Images are optimised automatically and location data is removed." />

    @if (session('duplicates'))
        <div class="alert alert-info pa-alert" role="status">
            <i class="bi bi-files" aria-hidden="true"></i>
            Already in the library, so not uploaded again:
            <ul class="mb-1 mt-1">
                @foreach (session('duplicates') as $duplicate)
                    <li>{{ $duplicate['name'] }} → <a href="{{ route('admin.media.edit', $duplicate['id']) }}">{{ $duplicate['existing'] }} (#{{ $duplicate['id'] }})</a></li>
                @endforeach
            </ul>
            <span class="small">To store a second copy anyway, tick “Upload even if the file is already in the library”.</span>
        </div>
    @endif
    @if (session('skipped'))
        <div class="alert alert-warning pa-alert" role="alert">
            <i class="bi bi-exclamation-triangle" aria-hidden="true"></i> Some files were not changed:
            <ul class="mb-0 mt-1">@foreach (session('skipped') as $skip)<li>{{ $skip }}</li>@endforeach</ul>
        </div>
    @endif

    @can('media.upload')
        <form method="POST" action="{{ route('admin.media.store') }}" enctype="multipart/form-data" class="pa-dropzone mb-4" data-dropzone>
            @csrf
            <i class="bi bi-cloud-arrow-up fs-2 text-body-secondary" aria-hidden="true"></i>
            <p class="mb-2"><label for="upload-files" class="fw-semibold">Drop files here or choose files to upload</label></p>
            <input id="upload-files" type="file" name="files[]" multiple class="form-control mx-auto mb-2" style="max-width: 28rem"
                   accept="{{ $extensions }}" required aria-describedby="upload-help">
            <p id="upload-help" class="small text-body-secondary mb-3">
                Images up to {{ intdiv(config('pacms.media.max_kb.image'), 1024) }} MB (JPG, PNG, WebP, GIF, AVIF{{ $svgAllowed ? ', SVG' : '' }}) · documents up to {{ intdiv(config('pacms.media.max_kb.document'), 1024) }} MB · video up to {{ intdiv(config('pacms.media.max_kb.video'), 1024) }} MB. Up to 20 files at once.
            </p>
            <div class="d-flex flex-wrap justify-content-center align-items-center gap-3">
                <div class="form-check mb-0">
                    <input type="hidden" name="private" value="0">
                    <input class="form-check-input" type="checkbox" name="private" value="1" id="upload-private">
                    <label class="form-check-label small" for="upload-private">Private (staff only, not publicly reachable)</label>
                </div>
                <div class="form-check mb-0">
                    <input type="hidden" name="allow_duplicates" value="0">
                    <input class="form-check-input" type="checkbox" name="allow_duplicates" value="1" id="upload-duplicates">
                    <label class="form-check-label small" for="upload-duplicates">Upload even if the file is already in the library</label>
                </div>
                <button type="submit" class="btn btn-primary">Upload</button>
            </div>
        </form>
    @endcan

    <form method="GET" class="pa-filters" role="search" aria-label="Filter media">
        <div>
            <label for="filter-q" class="form-label">Search</label>
            <input id="filter-q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" placeholder="Name, alt text, caption, credit, tag">
        </div>
        <div>
            <label for="filter-kind" class="form-label">Type</label>
            <select id="filter-kind" name="kind" class="form-select">
                <option value="">All types</option>
                @foreach (\App\Enums\MediaKind::cases() as $kind)
                    <option value="{{ $kind->value }}" @selected(($filters['kind'] ?? '') === $kind->value)>{{ $kind->label() }}</option>
                @endforeach
            </select>
        </div>
        @if ($categories->isNotEmpty())
            <div>
                <label for="filter-category" class="form-label">Category</label>
                <select id="filter-category" name="category" class="form-select">
                    <option value="">All categories</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((int) ($filters['category'] ?? 0) === $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        @if ($tags->isNotEmpty())
            <div>
                <label for="filter-tag" class="form-label">Tag</label>
                <select id="filter-tag" name="tag" class="form-select">
                    <option value="">All tags</option>
                    @foreach ($tags as $tag)
                        <option value="{{ $tag->id }}" @selected((int) ($filters['tag'] ?? 0) === $tag->id)>{{ $tag->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <div>
            <label for="filter-usage" class="form-label">Use</label>
            <select id="filter-usage" name="usage" class="form-select">
                <option value="">Used or not</option>
                <option value="used" @selected(($filters['usage'] ?? '') === 'used')>Used on the site</option>
                <option value="unused" @selected(($filters['usage'] ?? '') === 'unused')>Not used anywhere</option>
            </select>
        </div>
        <div>
            <label for="filter-visibility" class="form-label">Visibility</label>
            <select id="filter-visibility" name="visibility" class="form-select">
                <option value="">Public and private</option>
                <option value="public" @selected(($filters['visibility'] ?? '') === 'public')>Public</option>
                <option value="private" @selected(($filters['visibility'] ?? '') === 'private')>Private</option>
            </select>
        </div>
        <div>
            <label for="filter-sort" class="form-label">Sort</label>
            <select id="filter-sort" name="sort" class="form-select">
                @foreach (\App\Http\Controllers\Admin\MediaController::SORTS as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['sort'] ?? 'newest') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="d-flex flex-column justify-content-end gap-1 small">
            <div class="form-check mb-0">
                <input class="form-check-input" type="checkbox" name="needs_alt" value="1" id="filter-alt" @checked(! empty($filters['needs_alt']))>
                <label class="form-check-label" for="filter-alt">Needs alt text</label>
            </div>
            <div class="form-check mb-0">
                <input class="form-check-input" type="checkbox" name="mine" value="1" id="filter-mine" @checked(! empty($filters['mine']))>
                <label class="form-check-label" for="filter-mine">My uploads</label>
            </div>
        </div>
        <div class="pa-filters__actions">
            <button class="btn btn-secondary" type="submit">Apply</button>
            @if ($activeFilters || ($filters['sort'] ?? 'newest') !== 'newest')
                <a href="{{ route('admin.media.index') }}" class="btn btn-link">Reset</a>
            @endif
        </div>
    </form>

    @if ($items->isEmpty())
        <div class="card pa-card"><x-admin.empty-state icon="bi-images" title="No media found" message="{{ $activeFilters ? 'No files match these filters.' : 'Upload files above.' }}" /></div>
    @else
        @if ($canBulk)
            <form id="bulk-form" method="POST" action="{{ route('admin.media.bulk') }}" class="pa-bulk-bar" data-bulk data-confirm-delete="Delete the selected files? Files that are used on the site are skipped.">
                @csrf
                <div class="form-check mb-0">
                    <input class="form-check-input" type="checkbox" id="select-all" data-select-all="[data-bulk-item]">
                    <label class="form-check-label small" for="select-all">Select all on this page</label>
                </div>
                <span class="small text-body-secondary" data-bulk-count aria-live="polite">0 selected</span>
                <label for="bulk-action" class="visually-hidden">Action</label>
                <select id="bulk-action" name="action" class="form-select form-select-sm w-auto" required>
                    <option value="">Choose an action…</option>
                    @can('media.update')
                        @if ($categories->isNotEmpty())<option value="category">Add to category</option>@endif
                        @if ($tags->isNotEmpty())<option value="tag">Add tag</option>@endif
                        <option value="private">Make private</option>
                        <option value="public">Make public</option>
                    @endcan
                    @can('media.delete')<option value="delete">Delete (unused files only)</option>@endcan
                </select>
                <label for="bulk-term" class="visually-hidden">Category or tag</label>
                <select id="bulk-term" name="term_id" class="form-select form-select-sm w-auto">
                    <option value="">Category or tag…</option>
                    @if ($categories->isNotEmpty())
                        <optgroup label="Categories">@foreach ($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</optgroup>
                    @endif
                    @if ($tags->isNotEmpty())
                        <optgroup label="Tags">@foreach ($tags as $tag)<option value="{{ $tag->id }}">{{ $tag->name }}</option>@endforeach</optgroup>
                    @endif
                </select>
                <button type="submit" class="btn btn-sm btn-primary">Apply to selected</button>
            </form>
        @endif

        <p class="small text-body-secondary">{{ trans_choice(':count file|:count files', $items->total(), ['count' => $items->total()]) }}</p>
        <ul class="pa-media-grid list-unstyled" aria-label="Media items">
            @foreach ($items as $item)
                <li class="pa-media-grid__item">
                    @if ($canBulk)
                        <input type="checkbox" class="form-check-input pa-media-select" name="ids[]" value="{{ $item->id }}" form="bulk-form" data-bulk-item aria-label="Select {{ $item->original_name }}">
                    @endif
                    <a href="{{ route('admin.media.edit', $item) }}" class="pa-media-tile">
                        <span class="pa-media-tile__thumb">
                            @if ($item->isImage())
                                <img src="{{ $item->isPublic() ? $item->thumbnailUrl(320) : route('admin.media.file', $item) }}" alt="" loading="lazy" width="320" height="240">
                            @else
                                <i class="bi {{ $item->kind->icon() }}" aria-hidden="true"></i>
                            @endif
                        </span>
                        <span class="pa-media-tile__name">
                            @unless ($item->isPublic())<i class="bi bi-lock-fill" aria-label="Private"></i>@endunless
                            {{ $item->original_name }}
                            <span class="d-block small text-body-secondary">{{ $item->humanSize() }}{{ $item->width ? ' · '.$item->width.'×'.$item->height : '' }}</span>
                            @if ($item->isImage() && ! $item->alt && ! $item->is_decorative)
                                <span class="pa-badge pa-badge--warning d-block mt-1">Needs alt text</span>
                            @endif
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>
        <div class="mt-3">{{ $items->links() }}</div>
    @endif
</x-admin.layout>
