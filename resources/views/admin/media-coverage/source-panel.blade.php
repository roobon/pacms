{{-- Beside the coverage form (MediaCoverageType::adminPanel): the original link's last check. --}}
@php
    use App\Cms\Content\Types\MediaCoverageType;
    $availability = $item->availability;
    $badge = ['available' => 'success', 'unverified' => 'warning', 'unavailable' => 'danger'][$availability] ?? 'info';
@endphp
<section class="card pa-card mb-4" aria-labelledby="source-check-heading">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h2 id="source-check-heading" class="h6 mb-0">Original link</h2>
        @if ($item->source_url)
            <span class="pa-badge pa-badge--{{ $badge }}">{{ MediaCoverageType::AVAILABILITY[$availability] ?? $availability }}</span>
        @endif
    </div>
    <div class="card-body">
        @if (! $item->source_url)
            <p class="small text-body-secondary mb-0">No link to the original. Add one under Source.</p>
        @else
            <dl class="small mb-3 pa-meta">
                <dt>Last checked</dt>
                <dd>{{ $item->last_checked_at?->timezone($siteTimezone)->format('j M Y, H:i') ?? 'Not yet' }}</dd>
                @if ($item->http_status)
                    <dt>Answer</dt>
                    <dd>HTTP {{ $item->http_status }}</dd>
                @endif
                @if ($item->last_check_error)
                    <dt>Problem</dt>
                    <dd>{{ $item->last_check_error }}</dd>
                @endif
                @if ($item->consecutive_failures > 0)
                    <dt>Failed checks in a row</dt>
                    <dd>{{ $item->consecutive_failures }}</dd>
                @endif
                @if ($item->availability_override !== 'auto')
                    <dt>Shown</dt>
                    <dd>{{ MediaCoverageType::OVERRIDES[$item->availability_override] }}</dd>
                @endif
            </dl>
            <p class="small text-body-secondary">Checked about once a day. A link is marked unavailable after 3 failed checks in a row; sites that block automatic checks are never marked unavailable.</p>
            @can('update', $item)
                <form method="POST" action="{{ route('admin.media_coverage.check-source', $item) }}">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-secondary w-100"><i class="bi bi-arrow-repeat" aria-hidden="true"></i> Check now</button>
                </form>
            @endcan
        @endif
    </div>
</section>
