<x-admin.layout title="Redirects">
    <x-admin.page-header title="Redirects" subtitle="Send visitors (and search engines) from old addresses to new ones. Moving a published page adds one automatically." />

    <div class="row g-4">
        <div class="col-xl-8">
            <form method="GET" class="pa-filters" role="search" aria-label="Search redirects">
                <div><label for="filter-q" class="form-label">Search</label><input id="filter-q" type="search" name="q" value="{{ $q }}" class="form-control" placeholder="/old-address"></div>
                <div class="pa-filters__actions"><button class="btn btn-secondary" type="submit">Search</button></div>
            </form>
            <div class="card pa-card">
                @if ($redirects->isEmpty())
                    <x-admin.empty-state icon="bi-signpost-split" title="No redirects" />
                @else
                    <div class="table-responsive">
                        <table class="table pa-table align-middle mb-0">
                            <caption class="visually-hidden">Redirects</caption>
                            <thead><tr><th scope="col">From</th><th scope="col">To</th><th scope="col">Type</th><th scope="col">Hits</th><th scope="col"><span class="visually-hidden">Actions</span></th></tr></thead>
                            <tbody>
                                @foreach ($redirects as $redirect)
                                    <tr>
                                        <td><code>{{ $redirect->source_path }}</code></td>
                                        <td><code class="text-break">{{ $redirect->target_path }}</code></td>
                                        <td class="small">{{ $redirect->status_code }} @if ($redirect->is_auto)<span class="pa-badge pa-badge--neutral">auto</span>@endif</td>
                                        <td class="small">{{ number_format($redirect->hits) }}</td>
                                        <td class="text-end">
                                            <form method="POST" action="{{ route('admin.redirects.destroy', $redirect) }}" data-confirm="Remove the redirect from {{ $redirect->source_path }}?">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Remove<span class="visually-hidden"> {{ $redirect->source_path }}</span></button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer">{{ $redirects->links() }}</div>
                @endif
            </div>
        </div>
        <div class="col-xl-4">
            <form method="POST" action="{{ route('admin.redirects.store') }}" class="card pa-card" novalidate>
                @csrf
                <div class="card-header"><h2 class="h6 mb-0">Add redirect</h2></div>
                <div class="card-body">
                    <x-admin.field name="source_path" label="From (old address)" required placeholder="/old-page" />
                    <x-admin.field name="target_path" label="To" required placeholder="/new-page or https://…" />
                    <div class="mb-3">
                        <label for="field-status" class="form-label">Type</label>
                        <select id="field-status" name="status_code" class="form-select">
                            <option value="301">301 — moved permanently</option>
                            <option value="302">302 — temporary</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Add redirect</button>
                </div>
            </form>
        </div>
    </div>
</x-admin.layout>
