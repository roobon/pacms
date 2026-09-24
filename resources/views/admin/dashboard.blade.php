<x-admin.layout title="Dashboard">
    <x-admin.page-header title="Welcome, {{ auth()->user()->name }}" subtitle="Probha Aurora CMS — Phase 2 foundation. Content features arrive in the next phases." />

    <div class="row g-4">
        @if ($userCount !== null)
            <div class="col-sm-6 col-xl-3">
                <div class="pa-stat">
                    <i class="bi bi-people" aria-hidden="true"></i>
                    <div>
                        <p class="pa-stat__value">{{ number_format($userCount) }}</p>
                        <p class="pa-stat__label">Users</p>
                    </div>
                </div>
            </div>
        @endif
        <div class="col-sm-6 col-xl-3">
            <div class="pa-stat">
                <i class="bi bi-person-badge" aria-hidden="true"></i>
                <div>
                    <p class="pa-stat__value">{{ auth()->user()->getRoleNames()->map(fn ($r) => \App\Auth\PermissionCatalog::roleLabel($r))->join(', ') ?: '—' }}</p>
                    <p class="pa-stat__label">Your role</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mt-1">
        @if ($health !== null)
            <div class="col-xl-7">
                <section class="card pa-card h-100" aria-labelledby="health-heading">
                    <div class="card-header"><h2 id="health-heading" class="h6 mb-0">System health</h2></div>
                    <ul class="list-group list-group-flush">
                        @foreach ($health as $check)
                            <li class="list-group-item d-flex gap-3 align-items-start">
                                <x-admin.status-badge :status="$check['status']" />
                                <div>
                                    <strong>{{ $check['check'] }}</strong>
                                    <div class="text-body-secondary small">{{ $check['message'] }}</div>
                                    @if ($check['hint'] && $check['status'] !== 'ok')
                                        <div class="small"><i class="bi bi-arrow-return-right" aria-hidden="true"></i> {{ $check['hint'] }}</div>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </section>
            </div>
        @endif

        @if ($recentActivity !== null)
            <div class="col-xl-5">
                <section class="card pa-card h-100" aria-labelledby="activity-heading">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h2 id="activity-heading" class="h6 mb-0">Recent activity</h2>
                        <a href="{{ route('admin.activity.index') }}" class="small">View all</a>
                    </div>
                    @if ($recentActivity->isEmpty())
                        <x-admin.empty-state icon="bi-journal" title="No activity yet" />
                    @else
                        <ul class="list-group list-group-flush">
                            @foreach ($recentActivity as $log)
                                <li class="list-group-item">
                                    <div class="d-flex justify-content-between gap-2">
                                        <code class="small">{{ $log->action }}</code>
                                        <time class="small text-body-secondary" datetime="{{ $log->created_at->toIso8601String() }}">{{ $log->created_at->diffForHumans() }}</time>
                                    </div>
                                    <div class="small">{{ $log->user?->name ?? 'System' }}@if ($log->subject_label) · {{ $log->subject_label }}@endif</div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            </div>
        @endif
    </div>
</x-admin.layout>
