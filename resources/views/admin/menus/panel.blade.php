<x-admin.layout :title="'Mega panel: '.$title">
    <x-admin.page-header :title="'Mega panel: '.$title" subtitle="A wide panel that opens from this top-level item instead of a list of its sub-items: columns, headings, links, an image, a button… On phones the sub-items are listed instead (or the panel, if the item has none).">
        <x-slot:actions>
            <a href="{{ route('admin.menus.edit', $menu) }}">Back to {{ $menu->name }}</a>
        </x-slot:actions>
    </x-admin.page-header>

    <form id="panel-form" method="POST" action="{{ route('admin.menus.panel.update', [$menu, $item]) }}" novalidate>
        @csrf @method('PUT')
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <button type="submit" class="btn btn-primary">Save panel</button>
            <span class="small text-body-secondary">Saving shows it on the website at once.</span>
        </div>
    </form>
    @if ($item->is_mega)
        <form method="POST" action="{{ route('admin.menus.panel.destroy', [$menu, $item]) }}" class="mt-2" data-confirm="Remove the mega panel of “{{ $title }}”? The item shows its sub-items again.">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn-sm btn-outline-danger">Remove the panel</button>
        </form>
    @endif

    <x-admin.block-builder :blocks="$blocks" form="panel-form" context="global" heading="Panel content" />
</x-admin.layout>
