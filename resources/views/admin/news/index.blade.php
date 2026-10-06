<x-admin.layout title="News">
    <x-admin.page-header title="News" subtitle="News items appear in News blocks on pages and at /news/…">
        @can('create', \App\Models\News::class)
            <x-slot:actions>
                <a href="{{ route('admin.news.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg" aria-hidden="true"></i> New item</a>
            </x-slot:actions>
        @endcan
    </x-admin.page-header>

    <form method="GET" class="pa-filters" role="search" aria-label="Search news">
        <div><label for="filter-q" class="form-label">Search</label><input id="filter-q" type="search" name="q" value="{{ $q }}" class="form-control" placeholder="Title"></div>
        <div class="pa-filters__actions"><button class="btn btn-secondary" type="submit">Search</button></div>
    </form>

    <div class="card pa-card">
        @if ($items->isEmpty())
            <x-admin.empty-state icon="bi-newspaper" title="No news yet" />
        @else
            <div class="table-responsive">
                <table class="table pa-table align-middle mb-0">
                    <caption class="visually-hidden">News</caption>
                    <thead><tr><th scope="col">Title</th><th scope="col">Status</th><th scope="col">Published</th><th scope="col"><span class="visually-hidden">Actions</span></th></tr></thead>
                    <tbody>
                        @foreach ($items as $item)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.news.edit', $item) }}" class="fw-semibold">{{ $item->title }}</a>
                                    @if ($item->featured)<span class="pa-badge pa-badge--info ms-1">Featured</span>@endif
                                    <div class="small text-body-secondary">/news/{{ $item->slug }} · {{ $item->author?->name ?? '—' }}</div>
                                </td>
                                <td><x-admin.status-badge :status="$item->status->value" /></td>
                                <td class="small">{{ $item->published_at?->diffForHumans() ?? '—' }}</td>
                                <td class="text-end"><a href="{{ route('admin.news.edit', $item) }}" class="btn btn-sm btn-outline-secondary">Open<span class="visually-hidden"> {{ $item->title }}</span></a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer">{{ $items->links() }}</div>
        @endif
    </div>
</x-admin.layout>
