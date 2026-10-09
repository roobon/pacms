<x-admin.layout :title="$menu->name">
    <x-admin.page-header :title="$menu->name" :subtitle="'Key: '.$menu->slug.' · drag items to reorder, or use the arrow buttons; the right arrow places an item under the one above it.'">
        <x-slot:actions>
            <a href="{{ route('admin.menus.index') }}">All menus</a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="row g-4">
        <div class="col-xl-9">
            <div data-menu-builder data-config="{{ json_encode($config) }}">
                <p class="text-body-secondary">Loading the menu builder…</p>
            </div>
        </div>
        <aside class="col-xl-3">
            <section class="card pa-card mb-4" aria-labelledby="rename-heading">
                <div class="card-header"><h2 id="rename-heading" class="h6 mb-0">Name</h2></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.menus.update', $menu) }}">
                        @csrf @method('PUT')
                        <label for="menu-name" class="visually-hidden">Name</label>
                        <input id="menu-name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $menu->name) }}" required maxlength="191">
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <button type="submit" class="btn btn-sm btn-outline-secondary mt-2">Rename</button>
                    </form>
                    <p class="small text-body-secondary mt-3 mb-0">The key <code>{{ $menu->slug }}</code> never changes, so Menu blocks keep showing this menu.</p>
                </div>
            </section>
            <section class="card pa-card" aria-labelledby="delete-heading">
                <div class="card-header"><h2 id="delete-heading" class="h6 mb-0">Delete</h2></div>
                <div class="card-body small">
                    <form method="POST" action="{{ route('admin.menus.destroy', $menu) }}" data-confirm="Delete the menu “{{ $menu->name }}”? Menu blocks that show it become empty.">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-outline-danger">Delete menu</button>
                    </form>
                </div>
            </section>
        </aside>
    </div>

    @push('islands')
        @viteReactRefresh
        @vite('resources/js/admin/islands/menu-builder.jsx')
    @endpush
</x-admin.layout>
