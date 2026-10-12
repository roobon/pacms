@props(['name', 'choices', 'selected' => null, 'emptyLabel' => null, 'emptyHelp' => null, 'wide' => false])
{{-- Designs to choose from, as radio cards with a thumbnail (Phase 11S). Each choice has id,
     name, slug and description; shipped designs have a picture, the others an icon. --}}
{{-- Used inside a <fieldset> whose <legend> names the choice. --}}
{{-- "wide": headers and footers, whose pictures are long strips, one per row. --}}
<div @class(['pa-design-cards', 'pa-design-cards--wide' => $wide])>
    @if ($emptyLabel)
        <label class="pa-design-card">
            <input type="radio" class="pa-design-card__input" name="{{ $name }}" value="" @checked(blank($selected))>
            <span class="pa-design-card__picture pa-design-card__picture--empty" aria-hidden="true"><i class="bi bi-dash-square-dotted"></i></span>
            <span class="pa-design-card__name">{{ $emptyLabel }}</span>
            @if ($emptyHelp)<span class="pa-design-card__text">{{ $emptyHelp }}</span>@endif
        </label>
    @endif
    @foreach ($choices as $choice)
        @php($thumbnail = \App\Services\Starter\StarterKitService::thumbnail($choice->slug ?? null))
        <label class="pa-design-card">
            <input type="radio" class="pa-design-card__input" name="{{ $name }}" value="{{ $choice->id }}" @checked((int) $selected === $choice->id)>
            @if ($thumbnail)
                <img class="pa-design-card__picture" src="{{ $thumbnail }}" alt="" loading="lazy">
            @else
                <span class="pa-design-card__picture pa-design-card__picture--empty" aria-hidden="true"><i class="bi bi-layout-text-window"></i></span>
            @endif
            <span class="pa-design-card__name">{{ $choice->name }}@if (isset($choice->published_revision_id) && ! $choice->published_revision_id) <span class="badge text-bg-warning">not published yet</span>@endif</span>
            @if ($choice->description)<span class="pa-design-card__text">{{ $choice->description }}</span>@endif
        </label>
    @endforeach
</div>
