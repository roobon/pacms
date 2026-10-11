<x-admin.layout title="External sources">
    <x-admin.page-header title="External sources" subtitle="Feeds of other websites (JSON Feed, RSS or Atom, also YouTube channels and playlists). PACMS reads them on a schedule and stores their items; Feed blocks show them. Visitors never wait for another site.">
        <x-slot:actions>
            @can('facebook.connect')
                <a href="{{ route('admin.facebook.show') }}" class="btn btn-outline-primary"><i class="bi bi-facebook" aria-hidden="true"></i> Connect a Facebook Page</a>
            @endcan
            @can('external_sources.manage')
                <a href="{{ route('admin.external-sources.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg" aria-hidden="true"></i> Add a feed</a>
            @endcan
        </x-slot:actions>
    </x-admin.page-header>

    @foreach ($sources->filter(fn ($source) => $source->account?->expiresSoon()) as $expiring)
        <div class="alert alert-warning pa-alert" role="alert">
            <i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>
            The Facebook access token of “{{ $expiring->name }}” expires {{ $expiring->account->token_expires_at->diffForHumans() }}.
            @can('facebook.connect')<a href="{{ route('admin.facebook.show') }}">Connect the Page again</a> to renew it.@endcan
        </div>
    @endforeach

    <div class="card pa-card">
        @if ($sources->isEmpty())
            <x-admin.empty-state icon="bi-rss" title="No external sources yet" />
        @else
            <div class="table-responsive">
                <table class="table pa-table align-middle mb-0">
                    <caption class="visually-hidden">External sources</caption>
                    <thead><tr><th scope="col">Name</th><th scope="col">Status</th><th scope="col">Items</th><th scope="col">Last success</th><th scope="col">Next sync</th><th scope="col"><span class="visually-hidden">Actions</span></th></tr></thead>
                    <tbody>
                        @foreach ($sources as $source)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.external-sources.edit', $source) }}" class="fw-semibold">{{ $source->name }}</a>
                                    <div class="small text-body-secondary">{{ $providers[$source->provider]?->label() ?? $source->provider }}{{ $source->format ? ' · '.strtoupper($source->format) : '' }}</div>
                                </td>
                                <td>
                                    @if (! $source->isEnabled())
                                        <span class="pa-badge pa-badge--neutral">Disabled</span>
                                    @elseif ($source->last_status === 'error')
                                        <span class="pa-badge pa-badge--danger" title="{{ $source->last_error }}">Last sync failed</span>
                                    @elseif ($source->last_status === 'ok')
                                        <span class="pa-badge pa-badge--success">OK</span>
                                    @else
                                        <span class="pa-badge pa-badge--neutral">Not synced yet</span>
                                    @endif
                                </td>
                                <td class="small">{{ $source->items_count }}</td>
                                <td class="small">{{ $source->last_success_at?->diffForHumans() ?? '—' }}</td>
                                <td class="small">{{ $source->isEnabled() ? ($source->next_sync_at?->diffForHumans() ?? 'soon') : '—' }}</td>
                                <td class="text-end"><a href="{{ route('admin.external-sources.edit', $source) }}" class="btn btn-sm btn-outline-secondary">Open<span class="visually-hidden"> {{ $source->name }}</span></a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-admin.layout>
