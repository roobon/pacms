<x-admin.layout title="Templates">
    <x-admin.page-header title="Templates" subtitle="Saved blocks, sections and pages. Inserting a template makes an independent copy.">
        <x-slot:actions>
            <a href="{{ route('admin.block-templates.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg" aria-hidden="true"></i> New template</a>
        </x-slot:actions>
    </x-admin.page-header>

    <form method="GET" class="pa-filters" role="search" aria-label="Search templates">
        <div><label for="filter-q" class="form-label">Search</label><input id="filter-q" type="search" name="q" value="{{ $q }}" class="form-control" placeholder="Name"></div>
        <div class="pa-filters__actions"><button class="btn btn-secondary" type="submit">Search</button></div>
    </form>

    <div class="card pa-card">
        @if ($items->isEmpty())
            <x-admin.empty-state icon="bi-layout-wtf" title="No templates yet" message="Create one here, or select a block in a page’s builder and choose “Save as template”." />
        @else
            <div class="table-responsive">
                <table class="table pa-table align-middle mb-0">
                    <caption class="visually-hidden">Templates</caption>
                    <thead><tr><th scope="col">Name</th><th scope="col">Type</th><th scope="col">Category</th><th scope="col">In the builder</th><th scope="col"><span class="visually-hidden">Actions</span></th></tr></thead>
                    <tbody>
                        @foreach ($items as $item)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.block-templates.edit', $item) }}" class="fw-semibold">{{ $item->name }}</a>
                                    <div class="small text-body-secondary">{{ $item->description ?: '—' }} · {{ $item->creator?->name ?? '—' }}</div>
                                </td>
                                <td class="small">{{ \App\Models\BlockTemplate::SCOPES[$item->scope] ?? $item->scope }}</td>
                                <td class="small">{{ $item->category ?: '—' }}</td>
                                <td>{!! $item->status === 'published' ? '<span class="pa-badge pa-badge--success">Offered</span>' : '<span class="pa-badge pa-badge--neutral">Hidden</span>' !!}</td>
                                <td class="text-end"><a href="{{ route('admin.block-templates.edit', $item) }}" class="btn btn-sm btn-outline-secondary">Open<span class="visually-hidden"> {{ $item->name }}</span></a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer">{{ $items->links() }}</div>
        @endif
    </div>
</x-admin.layout>
