<x-admin.layout title="Content types">
    <x-admin.page-header title="Content types" subtitle="Your own kinds of content, such as success stories, resources or job openings. Each gets a list in the Content menu, a form with the fields you choose, and pages on the website.">
        <x-slot:actions>
            <a href="{{ route('admin.content-types.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg" aria-hidden="true"></i> New content type</a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="card pa-card">
        @if ($types->isEmpty())
            <x-admin.empty-state icon="bi-collection" title="No content types yet" />
        @else
            <div class="table-responsive">
                <table class="table pa-table align-middle mb-0">
                    <caption class="visually-hidden">Content types</caption>
                    <thead><tr><th scope="col">Name</th><th scope="col">Address</th><th scope="col">Fields</th><th scope="col">Items</th><th scope="col">Status</th><th scope="col"><span class="visually-hidden">Actions</span></th></tr></thead>
                    <tbody>
                        @foreach ($types as $type)
                            <tr>
                                <td>
                                    <i class="bi {{ $type->icon }} me-1" aria-hidden="true"></i>
                                    <a href="{{ route('admin.content-types.edit', $type) }}" class="fw-semibold">{{ $type->label }}</a>
                                    <div class="small text-body-secondary">{{ $type->workflow === 'managed' ? 'Active / inactive' : 'Editorial workflow' }}</div>
                                </td>
                                <td class="small">/{{ $type->route_prefix }}</td>
                                <td class="small">{{ count($type->fields ?? []) }}</td>
                                <td class="small">
                                    @if ($type->is_active)
                                        <a href="{{ route('admin.types.index', ['type' => $type->key]) }}">{{ $type->items_count }}</a>
                                    @else
                                        {{ $type->items_count }}
                                    @endif
                                </td>
                                <td><span class="pa-badge pa-badge--{{ $type->is_active ? 'success' : 'neutral' }}">{{ $type->is_active ? 'Active' : 'Disabled' }}</span></td>
                                <td class="text-end"><a href="{{ route('admin.content-types.edit', $type) }}" class="btn btn-sm btn-outline-secondary">Edit<span class="visually-hidden"> {{ $type->label }}</span></a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-admin.layout>
