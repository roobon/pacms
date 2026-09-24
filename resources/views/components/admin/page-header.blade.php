@props(['title', 'subtitle' => null])
<div class="pa-page-header">
    <div>
        <h1 class="pa-page-header__title">{{ $title }}</h1>
        @if ($subtitle)
            <p class="pa-page-header__subtitle">{{ $subtitle }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="pa-page-header__actions">{{ $actions }}</div>
    @endisset
</div>
