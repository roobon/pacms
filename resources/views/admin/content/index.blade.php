@php($key = $type->key())
<x-admin.layout :title="$type->label()">
    <x-admin.page-header :title="$type->label()" :subtitle="'Published '.strtolower($type->label()).' appear at /'.$type->routePrefix().' and in '.$type->label().' blocks on pages.'">
        @can($type->ability('create'))
            <x-slot:actions>
                <a href="{{ $type->adminUrl('create') }}" class="btn btn-primary"><i class="bi bi-plus-lg" aria-hidden="true"></i> New {{ $type->singular() }}</a>
            </x-slot:actions>
        @endcan
    </x-admin.page-header>

    <form method="GET" class="pa-filters" role="search" aria-label="Filter {{ strtolower($type->label()) }}">
        <div><label for="filter-q" class="form-label">Search</label><input id="filter-q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" placeholder="Title"></div>
        <div>
            <label for="filter-status" class="form-label">Status</label>
            <select id="filter-status" name="status" class="form-select">
                <option value="">All</option>
                @foreach (\App\Enums\ContentStatus::cases() as $status)
                    <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="pa-filters__actions"><button class="btn btn-secondary" type="submit">Filter</button></div>
    </form>

    <div class="card pa-card">
        @if ($items->isEmpty())
            <x-admin.empty-state :icon="$type->icon()" :title="'No '.strtolower($type->label()).' yet'" />
        @else
            <div class="table-responsive">
                <table class="table pa-table align-middle mb-0">
                    <caption class="visually-hidden">{{ $type->label() }}</caption>
                    <thead><tr><th scope="col">Title</th><th scope="col">Status</th><th scope="col">Published</th><th scope="col"><span class="visually-hidden">Actions</span></th></tr></thead>
                    <tbody>
                        @foreach ($items as $item)
                            <tr>
                                <td>
                                    <a href="{{ $type->adminUrl('edit', $item) }}" class="fw-semibold">{{ $item->title }}</a>
                                    @if ($item->featured)<span class="pa-badge pa-badge--info ms-1">Featured</span>@endif
                                    @if ($subtitle = $type->listSubtitle($item))<div class="small">{{ $subtitle }}</div>@endif
                                    <div class="small text-body-secondary">{{ $item->url() }} · {{ $item->author?->name ?? '—' }}</div>
                                </td>
                                <td>
                                    <x-admin.status-badge :status="$item->status" />
                                    @if ($item->publish_at)<span class="pa-badge pa-badge--warning">Scheduled</span>@endif
                                </td>
                                <td class="small">{{ $item->published_at?->diffForHumans() ?? '—' }}</td>
                                <td class="text-end"><a href="{{ $type->adminUrl('edit', $item) }}" class="btn btn-sm btn-outline-secondary">Open<span class="visually-hidden"> {{ $item->title }}</span></a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer">{{ $items->links() }}</div>
        @endif
    </div>
</x-admin.layout>
