@php
    $editing = $source->exists;
    $canManage = auth()->user()->can('external_sources.manage');
    $test = session('test');
@endphp
<x-admin.layout :title="$editing ? $source->name : 'Add an external source'">
    <x-admin.page-header :title="$editing ? $source->name : 'Add an external source'" :subtitle="$editing ? 'Key for blocks and the API: '.$source->slug : 'Paste the address of a feed: JSON Feed, RSS or Atom. YouTube: https://www.youtube.com/feeds/videos.xml?channel_id=… or ?playlist_id=…'">
        <x-slot:actions><a href="{{ route('admin.external-sources.index') }}">All sources</a></x-slot:actions>
    </x-admin.page-header>

    <div class="row g-4">
        <div class="col-lg-8">
            <form id="source-form" method="POST" action="{{ $editing ? route('admin.external-sources.update', $source) : route('admin.external-sources.store') }}" novalidate>
                @csrf
                @if ($editing) @method('PUT') @endif
                <input type="hidden" name="provider" value="{{ $source->provider }}">
                <fieldset @disabled(! $canManage)>
                    <section class="card pa-card mb-4" aria-labelledby="source-heading">
                        <div class="card-header"><h2 id="source-heading" class="h6 mb-0">Source</h2></div>
                        <div class="card-body">
                            <x-admin.field name="name" label="Name" :value="$source->name" required help="Shown under items when “Show the source name” is on, e.g. UN Environment." />
                            <x-admin.field name="config[feed_url]" label="Feed address" type="url" :value="$source->config['feed_url'] ?? ''" required placeholder="https://example.org/feed.json" />
                            <x-admin.field name="description" label="Note for editors (optional)" :value="$source->description" />
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="field-interval" class="form-label">Read it</label>
                                    <select id="field-interval" name="sync_interval_minutes" class="form-select">
                                        @foreach ($intervals as $minutes => $label)
                                            <option value="{{ $minutes }}" @selected((int) old('sync_interval_minutes', $source->sync_interval_minutes) === $minutes)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <x-admin.field name="max_items" label="Items to keep" type="number" :value="$source->max_items" help="The newest are kept (at most 200)." />
                                </div>
                            </div>
                            <div class="form-check mt-2">
                                <input type="hidden" name="enabled" value="0">
                                <input class="form-check-input" type="checkbox" name="enabled" value="1" id="field-enabled" @checked(old('enabled', $source->isEnabled()))>
                                <label class="form-check-label" for="field-enabled">Enabled</label>
                                <div class="form-text mt-0">A disabled source is not read, and Feed blocks that show it are empty.</div>
                            </div>
                        </div>
                    </section>
                </fieldset>
            </form>

            @if ($test)
                <section class="card pa-card mb-4" aria-labelledby="test-heading" role="status">
                    <div class="card-header"><h2 id="test-heading" class="h6 mb-0">Test result</h2></div>
                    <div class="card-body">
                        <p class="{{ $test['ok'] ? 'text-success' : 'text-danger' }} fw-semibold mb-2">
                            {{ $test['ok'] ? ($test['message'].($test['title'] ? ' — '.$test['title'] : '').' ('.strtoupper((string) $test['format']).')') : $test['message'] }}
                        </p>
                        @foreach ($test['items'] ?? [] as $item)
                            <p class="small mb-1">{{ $item['title'] ?? $item['link'] }} <span class="text-body-secondary">{{ isset($item['published_at']) ? \Illuminate\Support\Carbon::parse($item['published_at'])->toFormattedDayDateString() : '' }}</span></p>
                        @endforeach
                    </div>
                </section>
            @endif

            @if ($editing)
                <section class="card pa-card mb-4" aria-labelledby="items-heading">
                    <div class="card-header"><h2 id="items-heading" class="h6 mb-0">Newest stored items ({{ $source->items_count }})</h2></div>
                    <div class="card-body">
                        @forelse ($recent as $item)
                            <p class="small mb-2">
                                @if ($item->link)<a href="{{ $item->link }}" target="_blank" rel="noopener noreferrer">{{ $item->title ?? $item->link }}</a>@else{{ $item->title }}@endif
                                <span class="text-body-secondary">· {{ $item->published_at?->toFormattedDayDateString() ?? 'no date' }}</span>
                            </p>
                        @empty
                            <p class="small text-body-secondary mb-0">No items stored yet.</p>
                        @endforelse
                    </div>
                </section>
                <section class="card pa-card" aria-labelledby="log-heading">
                    <div class="card-header"><h2 id="log-heading" class="h6 mb-0">Recent syncs</h2></div>
                    <div class="table-responsive">
                        <table class="table pa-table mb-0 small">
                            <caption class="visually-hidden">Recent syncs</caption>
                            <thead><tr><th scope="col">When</th><th scope="col">Started by</th><th scope="col">Result</th><th scope="col">Items</th></tr></thead>
                            <tbody>
                                @forelse ($logs as $log)
                                    <tr>
                                        <td>{{ $log->created_at?->diffForHumans() }}</td>
                                        <td>{{ $log->trigger === 'schedule' ? 'Schedule' : 'Manual' }}</td>
                                        <td>{!! $log->status === 'ok' ? '<span class="text-success">OK</span>' : '<span class="text-danger">'.e($log->error ?? $log->status).'</span>' !!}</td>
                                        <td>{{ $log->items_fetched }} ({{ $log->items_created }} new) · {{ $log->duration_ms }} ms</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-body-secondary">No syncs yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif
        </div>

        <aside class="col-lg-4">
            <section class="card pa-card mb-4" aria-labelledby="actions-heading">
                <div class="card-header"><h2 id="actions-heading" class="h6 mb-0">{{ $editing ? 'Status' : 'Save' }}</h2></div>
                <div class="card-body">
                    @if ($editing)
                        <dl class="small mb-3">
                            <dt>Last success</dt><dd>{{ $source->last_success_at?->diffForHumans() ?? 'never' }}</dd>
                            <dt>Next sync</dt><dd>{{ $source->isEnabled() ? ($source->next_sync_at?->diffForHumans() ?? 'soon') : 'disabled' }}</dd>
                            @if ($source->last_status === 'error')
                                <dt class="text-danger">Last error</dt><dd class="text-danger">{{ $source->last_error }}<br><span class="text-body-secondary">Stored items keep showing; the next try waits longer each time.</span></dd>
                            @endif
                        </dl>
                    @endif
                    @if ($canManage)
                        <button type="submit" form="source-form" class="btn btn-primary w-100 mb-2">{{ $editing ? 'Save changes' : 'Add source' }}</button>
                        <button type="submit" form="source-form" formaction="{{ route('admin.external-sources.test') }}" class="btn btn-outline-secondary w-100">Test the address</button>
                    @endif
                    @if ($editing)
                        <form method="POST" action="{{ route('admin.external-sources.sync', $source) }}" class="mt-2">
                            @csrf
                            <button type="submit" class="btn btn-outline-primary w-100" @disabled(! $source->isEnabled())><i class="bi bi-arrow-repeat" aria-hidden="true"></i> Sync now</button>
                        </form>
                    @endif
                </div>
            </section>
            @if ($editing && $canManage)
                <section class="card pa-card" aria-labelledby="danger-heading">
                    <div class="card-header"><h2 id="danger-heading" class="h6 mb-0">Stored items</h2></div>
                    <div class="card-body small">
                        <form method="POST" action="{{ route('admin.external-sources.clear', $source) }}" data-confirm="Delete the stored items of “{{ $source->name }}”? They come back with the next sync.">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-secondary">Clear items</button>
                        </form>
                        <form method="POST" action="{{ route('admin.external-sources.destroy', $source) }}" class="mt-3" data-confirm="Delete the source “{{ $source->name }}”? Feed blocks that show it become empty.">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger">Delete source</button>
                        </form>
                    </div>
                </section>
            @endif
        </aside>
    </div>
</x-admin.layout>
