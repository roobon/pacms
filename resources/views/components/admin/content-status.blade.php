@props(['item'])
{{-- Status badge + live indicator for staged content (pages). --}}
<span class="d-inline-flex flex-wrap gap-1 align-items-center">
    @if ($item->isLive())
        <span class="pa-badge pa-badge--success"><i class="bi bi-broadcast" aria-hidden="true"></i> Live</span>
        @if ($item->has_unpublished_changes)
            <span class="pa-badge pa-badge--warning">Unpublished changes</span>
        @endif
    @endif
    @if (! $item->isLive() || $item->status->value !== 'published')
        @php($variant = match ($item->status->value) {
            'in_review' => 'info', 'approved' => 'info', 'archived' => 'neutral', default => 'neutral',
        })
        <span class="pa-badge pa-badge--{{ $variant }}">
            {{ $item->status->label() }}@if ($item->publish_at) · scheduled @endif
        </span>
    @endif
</span>
