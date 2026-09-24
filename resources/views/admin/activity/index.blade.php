<x-admin.layout title="Activity Log">
    <x-admin.page-header title="Activity Log" subtitle="Audit trail of sign-ins, security events and changes. Retained for {{ config('pacms.activity_log.retention_days') }} days." />

    <form method="GET" class="pa-filters" aria-label="Filter activity">
        <div>
            <label for="filter-action" class="form-label">Event type</label>
            <select id="filter-action" name="action" class="form-select">
                <option value="">All events</option>
                @foreach ($actionGroups as $group)
                    <option value="{{ $group }}." @selected(($filters['action'] ?? '') === $group.'.')>{{ ucfirst($group) }}</option>
                @endforeach
            </select>
        </div>
        <div class="pa-filters__actions">
            <button class="btn btn-secondary" type="submit">Filter</button>
        </div>
    </form>

    <div class="card pa-card">
        @if ($logs->isEmpty())
            <x-admin.empty-state icon="bi-journal-text" title="No activity recorded" />
        @else
            <div class="table-responsive">
                <table class="table pa-table align-middle mb-0">
                    <caption class="visually-hidden">Activity log</caption>
                    <thead>
                        <tr>
                            <th scope="col">When</th>
                            <th scope="col">Event</th>
                            <th scope="col">User</th>
                            <th scope="col">Subject</th>
                            <th scope="col">Details</th>
                            <th scope="col">IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($logs as $log)
                            <tr>
                                <td class="small text-nowrap"><time datetime="{{ $log->created_at->toIso8601String() }}" title="{{ $log->created_at }}">{{ $log->created_at->diffForHumans() }}</time></td>
                                <td><code>{{ $log->action }}</code></td>
                                <td class="small">{{ $log->user?->name ?? 'System / guest' }}</td>
                                <td class="small">{{ $log->subject_label ?? '—' }}</td>
                                <td class="small pa-log-properties">
                                    @if ($log->properties)
                                        <code>{{ json_encode($log->properties, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</code>
                                    @endif
                                </td>
                                <td class="small">{{ $log->ip ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer">{{ $logs->links() }}</div>
        @endif
    </div>
</x-admin.layout>
