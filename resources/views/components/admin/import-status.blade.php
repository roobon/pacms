@props(['job'])
@php
    [$label, $variant] = match ($job->status) {
        'awaiting_confirmation' => [$job->hasErrors() ? 'Has errors' : 'Waiting for you', $job->hasErrors() ? 'danger' : 'warning'],
        'importing' => ['Importing…', 'info'],
        'completed' => ['Imported', 'success'],
        default => ['Not imported', 'danger'],
    };
@endphp
<span {{ $attributes->class(['pa-badge', 'pa-badge--'.$variant]) }}>{{ $label }}</span>
