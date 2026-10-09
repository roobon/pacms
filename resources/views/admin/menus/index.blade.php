<x-admin.layout title="Menus">
    <x-admin.page-header title="Menus" subtitle="Links shown by Menu blocks in your header, footer and pages. Links to pages and content follow them when their address changes, and are hidden while they are not published." />

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card pa-card">
                @if ($menus->isEmpty())
                    <x-admin.empty-state icon="bi-list" title="No menus yet" />
                @else
                    <div class="table-responsive">
                        <table class="table pa-table align-middle mb-0">
                            <caption class="visually-hidden">Menus</caption>
                            <thead><tr><th scope="col">Name</th><th scope="col">Key</th><th scope="col">Items</th><th scope="col"><span class="visually-hidden">Actions</span></th></tr></thead>
                            <tbody>
                                @foreach ($menus as $menu)
                                    <tr>
                                        <td><a href="{{ route('admin.menus.edit', $menu) }}" class="fw-semibold">{{ $menu->name }}</a></td>
                                        <td class="small"><code>{{ $menu->slug }}</code></td>
                                        <td class="small">{{ $menu->items_count }}</td>
                                        <td class="text-end"><a href="{{ route('admin.menus.edit', $menu) }}" class="btn btn-sm btn-outline-secondary">Edit<span class="visually-hidden"> {{ $menu->name }}</span></a></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
        <div class="col-lg-4">
            <section class="card pa-card" aria-labelledby="new-menu-heading">
                <div class="card-header"><h2 id="new-menu-heading" class="h6 mb-0">New menu</h2></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.menus.store') }}">
                        @csrf
                        <label for="menu-name" class="form-label">Name</label>
                        <input id="menu-name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required maxlength="191" placeholder="e.g. Main menu">
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <button type="submit" class="btn btn-primary mt-3">Create menu</button>
                    </form>
                    <p class="small text-body-secondary mt-3 mb-0">Show a menu with a <strong>Menu</strong> block, usually in a header or footer (Design → Global blocks).</p>
                </div>
            </section>
        </div>
    </div>
</x-admin.layout>
