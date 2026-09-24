@props([
    'name',
    'label',
    'type' => 'text',
    'value' => null,
    'help' => null,
    'required' => false,
    'autocomplete' => null,
])
@php
    $id = 'field-'.str_replace(['[', ']', '.'], '-', $name);
    $errorKey = str_replace(['[', ']'], ['.', ''], $name);
    $hasError = $errors->has($errorKey);
    $describedBy = trim(($help ? $id.'-help ' : '').($hasError ? $id.'-error' : ''));
@endphp
<div class="mb-3">
    <label for="{{ $id }}" class="form-label">
        {{ $label }}
        @if ($required)<span class="pa-required">(required)</span>@endif
    </label>
    @if ($type === 'textarea')
        <textarea id="{{ $id }}" name="{{ $name }}" rows="3"
            {{ $attributes->class(['form-control', 'is-invalid' => $hasError]) }}
            @required($required) @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
            @if ($hasError) aria-invalid="true" @endif>{{ old($errorKey, $value) }}</textarea>
    @else
        <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}"
            @if ($type !== 'password') value="{{ old($errorKey, $value) }}" @endif
            {{ $attributes->class(['form-control', 'is-invalid' => $hasError]) }}
            @required($required) @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
            @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
            @if ($hasError) aria-invalid="true" @endif>
    @endif
    @if ($help)
        <div id="{{ $id }}-help" class="form-text">{{ $help }}</div>
    @endif
    @error($errorKey)
        <div id="{{ $id }}-error" class="invalid-feedback d-flex gap-1"><i class="bi bi-exclamation-circle" aria-hidden="true"></i>{{ $message }}</div>
    @enderror
</div>
