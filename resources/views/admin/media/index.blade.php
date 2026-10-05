<x-admin.layout title="Media Library">
    <x-admin.page-header title="Media Library" subtitle="Images, documents, video and audio. Images are optimised automatically and location data is removed." />

    @can('media.upload')
        <form method="POST" action="{{ route('admin.media.store') }}" enctype="multipart/form-data" class="pa-dropzone mb-4" data-dropzone>
            @csrf
            <i class="bi bi-cloud-arrow-up fs-2 text-body-secondary" aria-hidden="true"></i>
            <p class="mb-2"><label for="upload-files" class="fw-semibold">Drop files here or choose files to upload</label></p>
            <input id="upload-files" type="file" name="files[]" multiple class="form-control mx-auto mb-2" style="max-width: 28rem"
                   accept="{{ collect(array_keys(config('pacms.media.allowed')))->map(fn ($e) => '.'.$e)->implode(',') }}" required aria-describedby="upload-help">
            <p id="upload-help" class="small text-body-secondary mb-3">
                Images up to {{ intdiv(config('pacms.media.max_kb.image'), 1024) }} MB (JPG, PNG, WebP, GIF, AVIF) · documents up to {{ intdiv(config('pacms.media.max_kb.document'), 1024) }} MB · video up to {{ intdiv(config('pacms.media.max_kb.video'), 1024) }} MB. Up to 20 files at once.
            </p>
            <div class="d-flex justify-content-center align-items-center gap-3">
                <div class="form-check mb-0">
                    <input type="hidden" name="private" value="0">
                    <input class="form-check-input" type="checkbox" name="private" value="1" id="upload-private">
                    <label class="form-check-label small" for="upload-private">Private (staff only, not publicly reachable)</label>
                </div>
                <button type="submit" class="btn btn-primary">Upload</button>
            </div>
        </form>
    @endcan

    <form method="GET" class="pa-filters" role="search" aria-label="Filter media">
        <div>
            <label for="filter-q" class="form-label">Search</label>
            <input id="filter-q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" placeholder="File name, alt text, caption">
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
        <div class="pa-filters__actions">
            <button class="btn btn-secondary" type="submit">Filter</button>
            @if (array_filter($filters))
                <a href="{{ route('admin.media.index') }}" class="btn btn-link">Reset</a>
            @endif
        </div>
    </form>

    @if ($items->isEmpty())
        <div class="card pa-card"><x-admin.empty-state icon="bi-images" title="No media found" message="Upload files above, or change the filters." /></div>
    @else
        <ul class="pa-media-grid list-unstyled" aria-label="Media items">
            @foreach ($items as $item)
                <li>
                    <a href="{{ route('admin.media.edit', $item) }}" class="pa-media-tile">
                        <span class="pa-media-tile__thumb">
                            @if ($item->isImage() && $item->isPublic())
                                <img src="{{ $item->thumbnailUrl(320) }}" alt="" loading="lazy" width="320" height="240">
                            @else
                                <i class="bi {{ $item->kind->icon() }}" aria-hidden="true"></i>
                            @endif
                        </span>
                        <span class="pa-media-tile__name">
                            @unless ($item->isPublic())<i class="bi bi-lock-fill" aria-label="Private"></i>@endunless
                            {{ $item->original_name }}
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
