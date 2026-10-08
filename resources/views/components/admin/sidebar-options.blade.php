@props(['blocks', 'selected' => null])
{{-- Published global blocks offered as a sidebar: "Sidebar" kind first, then the others. --}}
@php
    [$sidebars, $others] = $blocks->partition(fn ($block) => $block->kind === 'sidebar');
@endphp
@foreach (['Sidebars' => $sidebars, 'Other global blocks' => $others] as $group => $items)
    @if ($items->isNotEmpty())
        <optgroup label="{{ $group }}">
            @foreach ($items as $block)
                <option value="{{ $block->id }}" @selected((int) $selected === $block->id)>{{ $block->name }}</option>
            @endforeach
        </optgroup>
    @endif
@endforeach
