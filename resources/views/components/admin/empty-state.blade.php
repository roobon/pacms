@props(['icon' => 'bi-inbox', 'title', 'message' => null])
<div class="pa-empty-state">
    <i class="bi {{ $icon }}" aria-hidden="true"></i>
    <p class="pa-empty-state__title">{{ $title }}</p>
    @if ($message)
        <p class="pa-empty-state__message">{{ $message }}</p>
    @endif
    {{ $slot }}
</div>
