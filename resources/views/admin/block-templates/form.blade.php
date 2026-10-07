@php($editing = $item->exists)
<x-admin.layout :title="$editing ? $item->name : 'New template'">
    <x-admin.page-header :title="$editing ? $item->name : 'New template'" subtitle="Editors insert templates from the builder’s “Templates” tab. Changing a template never changes pages that already used it.">
        <x-slot:actions><a href="{{ route('admin.block-templates.index') }}" class="btn btn-link">All templates</a></x-slot:actions>
    </x-admin.page-header>

    @error('lock_version')<div class="alert alert-danger pa-alert" role="alert">{{ $message }}</div>@enderror
    @error('blocks')<div class="alert alert-danger pa-alert" role="alert">{{ $message }}</div>@enderror

    <div class="row g-4">
        <div class="col-xl-8">
            <form id="template-form" method="POST" action="{{ $editing ? route('admin.block-templates.update', $item) : route('admin.block-templates.store') }}" novalidate>
                @csrf
                @if ($editing)
                    @method('PUT')
                    <input type="hidden" name="lock_version" value="{{ $item->lock_version }}">
                @endif
                <div class="card pa-card">
                    <div class="card-body">
                        <x-admin.field name="name" label="Name" :value="$item->name" required />
                        <x-admin.field name="description" label="Description" type="textarea" :value="$item->description" />
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="field-scope" class="form-label">Type</label>
                                <select id="field-scope" name="scope" class="form-select">
                                    @foreach (\App\Models\BlockTemplate::SCOPES as $value => $label)
                                        <option value="{{ $value }}" @selected(old('scope', $item->scope) === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <x-admin.field name="category" label="Category" :value="$item->category" help="Groups templates in the builder, e.g. Heroes." />
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <div class="col-xl-4">
            <section class="card pa-card" aria-labelledby="template-save-heading">
                <div class="card-header"><h2 id="template-save-heading" class="h6 mb-0">Availability</h2></div>
                <div class="card-body d-grid gap-2">
                    <div class="form-check">
                        <input type="hidden" name="status" value="draft" form="template-form">
                        <input class="form-check-input" type="checkbox" name="status" value="published" id="field-status" form="template-form" @checked(old('status', $item->status) === 'published')>
                        <label class="form-check-label" for="field-status">Offer in the builder</label>
                    </div>
                    <button type="submit" form="template-form" class="btn btn-primary">{{ $editing ? 'Save' : 'Create' }}</button>
                    @if ($editing)
                        @can('export.run')
                            <a href="{{ route('admin.export.template', $item) }}" class="btn btn-link p-0 text-start"><i class="bi bi-filetype-json" aria-hidden="true"></i> Export JSON</a>
                        @endcan
                        <form method="POST" action="{{ route('admin.block-templates.destroy', $item) }}" data-confirm="Delete “{{ $item->name }}”? Pages that used it keep their copy.">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-link text-danger p-0"><i class="bi bi-trash" aria-hidden="true"></i> Delete</button>
                        </form>
                    @endif
                </div>
            </section>
        </div>
    </div>

    <x-admin.block-builder :blocks="$blocks" form="template-form" context="template" heading="Template content"
        :owner="$editing ? ['type' => 'block_template', 'id' => $item->id] : null" :autosave="$autosave" />
</x-admin.layout>
