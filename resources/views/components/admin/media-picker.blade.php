@props(['name', 'label', 'media' => null, 'help' => null, 'disabled' => false, 'kind' => 'image', 'visibility' => null])
@php($id = 'picker-'.str_replace(['[', ']', '.'], '-', $name))
@php($errorKey = str_replace(['[', ']'], ['.', ''], $name))
{{-- Progressive enhancement: the React media picker island (resources/js/admin/islands/media-picker.jsx)
     mounts on [data-media-picker]; without JS the current image is still shown. --}}
<div class="mb-3">
    <span class="form-label d-block" id="{{ $id }}-label">{{ $label }}</span>
    <div class="pa-media-field" data-media-picker
         data-name="{{ $name }}"
         data-label="{{ $label }}"
         data-kind="{{ $kind }}"
         data-visibility="{{ $visibility }}"
         data-value="{{ old($errorKey, $media?->id) }}"
         data-preview="{{ $media?->thumbnailUrl(640) }}"
         data-alt="{{ $media?->alt }}"
         data-filename="{{ $media?->original_name }}"
         data-endpoint="{{ route('admin.api.media.index') }}"
         data-upload-endpoint="{{ route('admin.api.media.store') }}"
         data-can-upload="{{ auth()->user()->can('media.upload') ? '1' : '0' }}"
         data-disabled="{{ $disabled ? '1' : '0' }}"
         aria-labelledby="{{ $id }}-label">
        <input type="hidden" name="{{ $name }}" value="{{ old($errorKey, $media?->id) }}">
        @if ($media && $kind !== 'image')
            <p class="mb-0"><i class="bi {{ $kind === 'video' ? 'bi-film' : 'bi-file-earmark-text' }} me-1" aria-hidden="true"></i>{{ $media->original_name }}@unless ($media->isPublic()) <span class="pa-badge">Private</span>@endunless</p>
        @elseif ($media)
            <img src="{{ $media->thumbnailUrl(640) }}" alt="{{ $media->alt }}" class="pa-media-field__preview">
        @else
            <p class="text-body-secondary small mb-0">No {{ $kind }} selected.</p>
        @endif
    </div>
    @if ($help)
        <div class="form-text">{{ $help }}</div>
    @endif
    @error($errorKey)
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
</div>
@pushOnce('islands')
    @viteReactRefresh
    @vite('resources/js/admin/islands/media-picker.jsx')
@endPushOnce
