<x-admin.layout title="Custom blocks">
    <x-admin.page-header title="Custom blocks" subtitle="Your own block types: a form for editors plus a layout built from existing blocks. No code needed.">
        <x-slot:actions>
            <a href="{{ route('admin.block-types.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg" aria-hidden="true"></i> New block type</a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="card pa-card">
        @if ($items->isEmpty())
            <x-admin.empty-state icon="bi-puzzle" title="No custom blocks yet" message="Example: a “Staff profile” block with name, photo, role and biography fields." />
        @else
            <div class="table-responsive">
                <table class="table pa-table align-middle mb-0">
                    <caption class="visually-hidden">Custom block types</caption>
                    <thead><tr><th scope="col">Block</th><th scope="col">Status</th><th scope="col">Version</th><th scope="col">Used</th><th scope="col"><span class="visually-hidden">Actions</span></th></tr></thead>
                    <tbody>
                        @foreach ($items as $item)
                            <tr>
                                <td>
                                    <i class="bi {{ $item->icon }} me-1" aria-hidden="true"></i>
                                    <a href="{{ route('admin.block-types.edit', $item) }}" class="fw-semibold">{{ $item->name }}</a>
                                    <div class="small text-body-secondary">{{ $item->slug }}</div>
                                </td>
                                <td>
                                    <x-admin.status-badge :status="$item->status" />
                                    @if ($item->published_revision_id && $item->has_unpublished_changes)<span class="pa-badge pa-badge--warning ms-1">Unpublished changes</span>@endif
                                </td>
                                <td class="small">{{ $item->version ?: '—' }}</td>
                                <td class="small">{{ trans_choice(':count block|:count blocks', $usage[$item->id] ?? 0, ['count' => $usage[$item->id] ?? 0]) }}</td>
                                <td class="text-end"><a href="{{ route('admin.block-types.edit', $item) }}" class="btn btn-sm btn-outline-secondary">Open<span class="visually-hidden"> {{ $item->name }}</span></a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-admin.layout>
