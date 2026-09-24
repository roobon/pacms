<x-admin.layout title="Pages">
    <x-admin.page-header title="Pages" subtitle="The site's pages, in URL order. Sub-pages are indented under their parent.">
        @can('create', \App\Models\Page::class)
            <x-slot:actions>
                <a href="{{ route('admin.pages.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg" aria-hidden="true"></i> New page</a>
            </x-slot:actions>
        @endcan
    </x-admin.page-header>

    <form method="GET" class="pa-filters" role="search" aria-label="Filter pages">
        <div>
            <label for="filter-q" class="form-label">Search</label>
            <input id="filter-q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" placeholder="Title or URL">
        </div>
        <div>
            <label for="filter-status" class="form-label">Status</label>
            <select id="filter-status" name="status" class="form-select">
                <option value="">Any status</option>
                @foreach (\App\Enums\ContentStatus::cases() as $status)
                    <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="pa-filters__actions">
            <button class="btn btn-secondary" type="submit">Filter</button>
            @if (array_filter($filters))
                <a href="{{ route('admin.pages.index') }}" class="btn btn-link">Reset</a>
            @endif
        </div>
    </form>

    <div class="card pa-card">
        @if ($pages->isEmpty())
            <x-admin.empty-state icon="bi-file-earmark-richtext" title="No pages yet" message="Create the first page of the website.">
                @can('create', \App\Models\Page::class)
                    <a href="{{ route('admin.pages.create') }}" class="btn btn-primary mt-3">New page</a>
                @endcan
            </x-admin.empty-state>
        @else
            <div class="table-responsive">
                <table class="table pa-table align-middle mb-0">
                    <caption class="visually-hidden">Pages</caption>
                    <thead>
                        <tr>
                            <th scope="col">Title</th>
                            <th scope="col">Status</th>
                            <th scope="col">Author</th>
                            <th scope="col">Updated</th>
                            <th scope="col"><span class="visually-hidden">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pages as $page)
                            <tr>
                                <td>
                                    <div style="padding-left: {{ $page->depth() * 1.25 }}rem">
                                        @if ($page->depth() > 0)<i class="bi bi-arrow-return-right text-body-secondary me-1" aria-hidden="true"></i>@endif
                                        <a href="{{ route('admin.pages.edit', $page) }}" class="fw-semibold">{{ $page->title }}</a>
                                        @if ($page->id === $homepageId)
                                            <span class="pa-badge pa-badge--info ms-1"><i class="bi bi-house" aria-hidden="true"></i> Homepage</span>
                                        @endif
                                        <div class="small text-body-secondary">/{{ $page->path }}</div>
                                    </div>
                                </td>
                                <td><x-admin.content-status :item="$page" /></td>
                                <td class="small">{{ $page->author?->name ?? '—' }}</td>
                                <td class="small text-nowrap">{{ $page->updated_at->diffForHumans() }}</td>
                                <td class="text-end text-nowrap">
                                    @if ($page->isLive())
                                        <a href="{{ url($page->publicUrl()) }}" class="btn btn-sm btn-link" target="_blank" rel="noopener">View<span class="visually-hidden"> {{ $page->title }} (opens in new tab)</span></a>
                                    @endif
                                    <a href="{{ route('admin.pages.edit', $page) }}" class="btn btn-sm btn-outline-secondary">
                                        @can('update', $page) Edit @else Open @endcan<span class="visually-hidden"> {{ $page->title }}</span>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer">{{ $pages->links() }}</div>
        @endif
    </div>
</x-admin.layout>
