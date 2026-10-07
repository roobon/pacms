<x-admin.layout title="Global blocks">
    <x-admin.page-header title="Global blocks" subtitle="Shared sections placed on many pages. Publish a change once and every page shows it.">
        <x-slot:actions>
            <a href="{{ route('admin.global-blocks.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg" aria-hidden="true"></i> New global block</a>
        </x-slot:actions>
    </x-admin.page-header>

    <form method="GET" class="pa-filters" role="search" aria-label="Search global blocks">
        <div><label for="filter-q" class="form-label">Search</label><input id="filter-q" type="search" name="q" value="{{ $q }}" class="form-control" placeholder="Name"></div>
        <div class="pa-filters__actions"><button class="btn btn-secondary" type="submit">Search</button></div>
    </form>

    <div class="card pa-card">
        @if ($items->isEmpty())
            <x-admin.empty-state icon="bi-globe2" title="No global blocks yet" message="Create one here, or select blocks in a page’s builder and choose “Convert to global block”." />
        @else
            <div class="table-responsive">
                <table class="table pa-table align-middle mb-0">
                    <caption class="visually-hidden">Global blocks</caption>
                    <thead><tr><th scope="col">Name</th><th scope="col">Status</th><th scope="col">Used</th><th scope="col"><span class="visually-hidden">Actions</span></th></tr></thead>
                    <tbody>
                        @foreach ($items as $item)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.global-blocks.edit', $item) }}" class="fw-semibold">{{ $item->name }}</a>
                                    @if ($item->description)<div class="small text-body-secondary">{{ $item->description }}</div>@endif
                                </td>
                                <td>
                                    <x-admin.status-badge :status="$item->isPublished() ? 'published' : 'draft'" />
                                    @if ($item->isPublished() && $item->has_unpublished_changes)<span class="pa-badge pa-badge--warning ms-1">Unpublished changes</span>@endif
                                </td>
                                <td class="small">{{ trans_choice(':count place|:count places', $usage[$item->id] ?? 0, ['count' => $usage[$item->id] ?? 0]) }}</td>
                                <td class="text-end"><a href="{{ route('admin.global-blocks.edit', $item) }}" class="btn btn-sm btn-outline-secondary">Open<span class="visually-hidden"> {{ $item->name }}</span></a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer">{{ $items->links() }}</div>
        @endif
    </div>
</x-admin.layout>
