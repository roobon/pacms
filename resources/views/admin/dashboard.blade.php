@php
    $statCards = [
        'pages' => ['Pages', 'bi-file-earmark-richtext', 'admin.pages.index'],
        'live' => ['Published pages', 'bi-broadcast', 'admin.pages.index'],
        'media' => ['Media files', 'bi-images', 'admin.media.index'],
        'users' => ['Users', 'bi-people', 'admin.users.index'],
    ];
    $roles = auth()->user()->getRoleNames()->map(fn ($r) => \App\Auth\PermissionCatalog::roleLabel($r))->join(', ') ?: '—';
    $tz = app(\App\Services\Settings\SettingsService::class)->get('site', 'timezone', 'UTC');
@endphp
<x-admin.layout title="Dashboard">
    <x-admin.page-header title="Welcome, {{ auth()->user()->name }}" subtitle="Signed in as {{ $roles }}.">
        @can('create', \App\Models\Page::class)
            <x-slot:actions>
                <a href="{{ route('admin.pages.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg" aria-hidden="true"></i> New page</a>
            </x-slot:actions>
        @endcan
    </x-admin.page-header>

    @if ($stats !== [])
        <div class="row g-4">
            @foreach ($stats as $key => $value)
                @php([$label, $icon, $route] = $statCards[$key])
                <div class="col-sm-6 col-xl-3">
                    <a href="{{ route($route) }}" class="pa-stat text-decoration-none">
                        <i class="bi {{ $icon }}" aria-hidden="true"></i>
                        <div>
                            <p class="pa-stat__value">{{ number_format($value) }}</p>
                            <p class="pa-stat__label">{{ $label }}</p>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    @endif

    <div class="row g-4 mt-1">
        @if ($awaitingReview !== null)
            <div class="col-xl-6">
                <section class="card pa-card h-100" aria-labelledby="review-heading">
                    <div class="card-header"><h2 id="review-heading" class="h6 mb-0">Waiting for your review</h2></div>
                    @if ($awaitingReview->isEmpty())
                        <x-admin.empty-state icon="bi-check2-circle" title="Nothing to review" message="Pages submitted for review will appear here." />
                    @else
                        <ul class="list-group list-group-flush">
                            @foreach ($awaitingReview as $page)
                                <li class="list-group-item d-flex justify-content-between gap-2">
                                    <span>
                                        <a href="{{ route('admin.pages.edit', $page) }}" class="fw-semibold">{{ $page->title }}</a>
                                        <span class="d-block small text-body-secondary">by {{ $page->author?->name ?? '—' }}</span>
                                    </span>
                                    <time class="small text-body-secondary text-nowrap" datetime="{{ $page->updated_at->toIso8601String() }}">{{ $page->updated_at->diffForHumans() }}</time>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            </div>
        @endif

        @if ($myDrafts !== null)
            <div class="col-xl-6">
                <section class="card pa-card h-100" aria-labelledby="drafts-heading">
                    <div class="card-header"><h2 id="drafts-heading" class="h6 mb-0">Your drafts</h2></div>
                    @if ($myDrafts->isEmpty())
                        <x-admin.empty-state icon="bi-pencil-square" title="No drafts" />
                    @else
                        <ul class="list-group list-group-flush">
                            @foreach ($myDrafts as $page)
                                <li class="list-group-item d-flex justify-content-between gap-2">
                                    <a href="{{ route('admin.pages.edit', $page) }}">{{ $page->title }}</a>
                                    <span class="small text-body-secondary text-nowrap">{{ $page->updated_at->diffForHumans() }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            </div>
        @endif

        @if ($scheduled !== null && $scheduled->isNotEmpty())
            <div class="col-xl-6">
                <section class="card pa-card h-100" aria-labelledby="scheduled-heading">
                    <div class="card-header"><h2 id="scheduled-heading" class="h6 mb-0">Scheduled to publish</h2></div>
                    <ul class="list-group list-group-flush">
                        @foreach ($scheduled as $page)
                            <li class="list-group-item d-flex justify-content-between gap-2">
                                <a href="{{ route('admin.pages.edit', $page) }}">{{ $page->title }}</a>
                                <span class="small text-body-secondary text-nowrap">{{ $page->publish_at?->timezone($tz)->format('j M Y, H:i') }}</span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            </div>
        @endif

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
