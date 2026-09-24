@props(['status'])
@php
    $value = $status instanceof \BackedEnum ? $status->value : (string) $status;
    $label = $status instanceof \App\Enums\UserStatus ? $status->label() : ucwords(str_replace('_', ' ', $value));
    $variant = match ($value) {
        'active', 'published', 'ok' => 'success',
        'suspended', 'rejected', 'fail' => 'danger',
        'warning', 'scheduled' => 'warning',
        default => 'neutral',
    };
@endphp
<span {{ $attributes->class(['pa-badge', 'pa-badge--'.$variant]) }}>{{ $label }}</span>
