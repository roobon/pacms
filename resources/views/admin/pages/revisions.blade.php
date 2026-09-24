<x-admin.layout :title="'Revisions: '.$page->title">
    <x-admin.page-header :title="'Revisions: '.$page->title" subtitle="Every save and publish is kept. Restoring copies an old version into a new draft — history is never lost.">
        <x-slot:actions>
            <a href="{{ route('admin.pages.edit', $page) }}" class="btn btn-outline-secondary">Back to page</a>
        </x-slot:actions>
    </x-admin.page-header>

    @if ($compare)
        <section class="card pa-card mb-4" aria-labelledby="compare-heading">
            <div class="card-header"><h2 id="compare-heading" class="h6 mb-0">Changes from #{{ $compare['from']->number }} to #{{ $compare['to']->number }}</h2></div>
            @if ($compare['changes'] === [])
                <x-admin.empty-state icon="bi-check2-circle" title="No differences" />
            @else
                <div class="table-responsive">
                    <table class="table pa-table mb-0">
                        <thead><tr><th scope="col">Field</th><th scope="col">#{{ $compare['from']->number }}</th><th scope="col">#{{ $compare['to']->number }}</th></tr></thead>
                        <tbody>
                            @foreach ($compare['changes'] as $change)
                                <tr>
                                    <th scope="row"><code>{{ $change['field'] }}</code></th>
                                    <td class="pa-diff-old">{{ is_bool($change['from']) ? ($change['from'] ? 'yes' : 'no') : ($change['from'] ?? '—') }}</td>
                                    <td class="pa-diff-new">{{ is_bool($change['to']) ? ($change['to'] ? 'yes' : 'no') : ($change['to'] ?? '—') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @endif

    <form method="GET" class="card pa-card">
        <div class="table-responsive">
            <table class="table pa-table align-middle mb-0">
                <caption class="visually-hidden">Revisions — choose two to compare</caption>
                <thead>
                    <tr>
                        <th scope="col">From</th>
                        <th scope="col">To</th>
                        <th scope="col">Revision</th>
                        <th scope="col">By</th>
                        <th scope="col">When</th>
                        <th scope="col"><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($revisions as $i => $revision)
                        <tr>
                            <td><input class="form-check-input" type="radio" name="from" value="{{ $revision->number }}" aria-label="Compare from #{{ $revision->number }}" @checked((int) request('from', $revisions[1]->number ?? 0) === $revision->number)></td>
                            <td><input class="form-check-input" type="radio" name="to" value="{{ $revision->number }}" aria-label="Compare to #{{ $revision->number }}" @checked((int) request('to', $revisions[0]->number ?? 0) === $revision->number)></td>
                            <td>
                                <strong>#{{ $revision->number }}</strong>
                                <span @class(['pa-badge ms-1', 'pa-badge--success' => $revision->kind->value === 'published', 'pa-badge--neutral' => $revision->kind->value !== 'published'])>{{ $revision->kind->label() }}</span>
                                @if ($page->published_revision_id === $revision->id)<span class="pa-badge pa-badge--success">Live</span>@endif
                                <div class="small text-body-secondary">{{ $revision->summary }}</div>
                            </td>
                            <td class="small">{{ $revision->author?->name ?? 'System' }}</td>
                            <td class="small text-nowrap"><time datetime="{{ $revision->created_at->toIso8601String() }}">{{ $revision->created_at->diffForHumans() }}</time></td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('admin.pages.preview', ['page' => $page, 'revision' => $revision->number]) }}" class="btn btn-sm btn-link" target="_blank" rel="noopener">Preview<span class="visually-hidden"> #{{ $revision->number }} (opens in new tab)</span></a>
                                @if ($canRestore)
                                    <button type="submit" form="restore-{{ $revision->id }}" class="btn btn-sm btn-outline-secondary">Restore<span class="visually-hidden"> #{{ $revision->number }}</span></button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center">
            <button type="submit" class="btn btn-secondary btn-sm">Compare selected</button>
            {{ $revisions->links() }}
        </div>
    </form>

    @if ($canRestore)
        @foreach ($revisions as $revision)
            <form id="restore-{{ $revision->id }}" method="POST" action="{{ route('admin.pages.revisions.restore', [$page, $revision]) }}" class="d-none"
                  data-confirm="Restore revision #{{ $revision->number }} as a new draft? The live page does not change until you publish.">
                @csrf
            </form>
        @endforeach
    @endif
</x-admin.layout>
