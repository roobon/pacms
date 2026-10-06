<x-admin.layout title="New block type">
    <x-admin.page-header title="New block type" subtitle="Start with a name. You add the fields and build the layout on the next screen.">
        <x-slot:actions><a href="{{ route('admin.block-types.index') }}" class="btn btn-link">All custom blocks</a></x-slot:actions>
    </x-admin.page-header>

    <form method="POST" action="{{ route('admin.block-types.store') }}" class="card pa-card" style="max-width: 40rem" novalidate>
        @csrf
        <div class="card-body">
            <x-admin.field name="name" label="Name" :value="$item->name" required help="Shown to editors in the block palette, e.g. “Staff profile”." />
            <x-admin.field name="slug" label="Key" help="Optional — generated from the name. Stored as custom/<key> and cannot be changed later." />
            <x-admin.field name="icon" label="Icon" :value="$item->icon" help="A Bootstrap Icons name, e.g. bi-person-badge." />
            <x-admin.field name="description" label="Description" type="textarea" />
        </div>
        <div class="card-footer"><button type="submit" class="btn btn-primary">Create block type</button></div>
    </form>
</x-admin.layout>
