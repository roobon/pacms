<x-admin.layout :title="$item->name">
    <x-admin.page-header :title="$item->name" :subtitle="$item->slug.($item->version ? ' · version '.$item->version : ' · never published')">
        <x-slot:actions><a href="{{ route('admin.block-types.index') }}" class="btn btn-link">All custom blocks</a></x-slot:actions>
    </x-admin.page-header>

    @foreach (['lock_version', 'blocks', 'block_type', 'status'] as $key)
        @error($key)<div class="alert alert-danger pa-alert" role="alert">{{ $message }}</div>@enderror
    @endforeach

    <div class="row g-4">
        <div class="col-xl-8">
            <form id="type-form" method="POST" action="{{ route('admin.block-types.update', $item) }}" novalidate>
                @csrf @method('PUT')
                <input type="hidden" name="lock_version" value="{{ $item->lock_version }}">
                <div class="card pa-card">
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-7"><x-admin.field name="name" label="Name" :value="$item->name" required /></div>
                            <div class="col-md-5"><x-admin.field name="icon" label="Icon" :value="$item->icon" help="Bootstrap Icons name." /></div>
                        </div>
                        <x-admin.field name="description" label="Description" type="textarea" :value="$item->description" help="Shown to editors when they select this block." />
                    </div>
                </div>
            </form>
        </div>
        <div class="col-xl-4">
            <section class="card pa-card" aria-labelledby="type-publish-heading">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h2 id="type-publish-heading" class="h6 mb-0">Publishing</h2>
                    <x-admin.status-badge :status="$item->status" />
                </div>
                <div class="card-body d-grid gap-2">
                    <p class="small mb-1">Used in {{ trans_choice(':count block|:count blocks', $usage, ['count' => $usage]) }} on pages and templates.</p>
                    <button type="submit" form="type-form" class="btn btn-primary">Save</button>
                    @if ($item->has_unpublished_changes)
                        <form method="POST" action="{{ route('admin.block-types.publish', $item) }}">
                            @csrf
                            <button type="submit" class="btn btn-success w-100">{{ $item->published_revision_id ? 'Publish changes' : 'Publish' }}</button>
                        </form>
                        <p class="form-text mb-0">Save first. Publishing updates every block of this type; values editors entered are kept.</p>
                    @endif
                    @if ($item->published_revision_id)
                        <form method="POST" action="{{ route('admin.block-types.toggle', $item) }}">
                            @csrf
                            <button type="submit" class="btn btn-outline-secondary w-100">{{ $item->status === 'disabled' ? 'Enable' : 'Disable (keep existing blocks)' }}</button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('admin.block-types.destroy', $item) }}" data-confirm="Delete “{{ $item->name }}”?">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-link text-danger p-0" @disabled($usage > 0)><i class="bi bi-trash" aria-hidden="true"></i> Delete</button>
                    </form>
                </div>
            </section>
        </div>
    </div>

    <x-admin.block-builder :blocks="$blocks" form="type-form" context="structure" heading="Fields and layout"
        :fields="$item->fields ?? []" :owner="['type' => 'block_type', 'id' => $item->id]" :autosave="$autosave" />
</x-admin.layout>
