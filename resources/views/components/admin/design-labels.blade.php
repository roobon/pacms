@props(['number' => null, 'width' => 'fixed'])
{{-- A template's labels: its serial number (shipped designs only, e.g. P-07) and its width. --}}
<span class="pa-design-labels">
    @if ($number)<span class="pa-design-labels__number">{{ $number }}</span>@endif
    <span class="pa-design-labels__width pa-design-labels__width--{{ $width === 'full' ? 'full' : 'fixed' }}">
        <i class="bi {{ $width === 'full' ? 'bi-arrows-expand-vertical' : 'bi-arrows-collapse-vertical' }}" aria-hidden="true"></i>
        {{ \App\Services\Starter\StarterKitService::WIDTHS[$width === 'full' ? 'full' : 'fixed'] }}
    </span>
</span>
