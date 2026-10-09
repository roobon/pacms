@php($editing = $item->exists)
<x-admin.layout :title="$editing ? $item->name : 'New global block'">
    <x-admin.page-header :title="$editing ? $item->name : 'New global block'" subtitle="A shared section: place it on pages from the builder’s “Global” tab.">
        <x-slot:actions><a href="{{ route('admin.global-blocks.index') }}" class="btn btn-link">All global blocks</a></x-slot:actions>
    </x-admin.page-header>

    @error('global_block')<div class="alert alert-danger pa-alert" role="alert">{{ $message }}</div>@enderror
    @error('lock_version')<div class="alert alert-danger pa-alert" role="alert">{{ $message }}</div>@enderror

    <div class="row g-4">
        <div class="col-xl-8">
            <form id="global-form" method="POST" action="{{ $editing ? route('admin.global-blocks.update', $item) : route('admin.global-blocks.store') }}" novalidate>
                @csrf
                @if ($editing)
                    @method('PUT')
                    <input type="hidden" name="lock_version" value="{{ $item->lock_version }}">
                @endif
                <div class="card pa-card">
                    <div class="card-body">
                        <x-admin.field name="name" label="Name" :value="$item->name" required help="Only editors see the name." />
                        <x-admin.field name="slug" label="Key" :value="$item->slug" help="Optional — generated from the name. Lowercase letters, numbers and hyphens." />
                        <x-admin.field name="description" label="Description" type="textarea" :value="$item->description" help="Helps editors pick the right block in the builder." />
                        <div class="mb-0">
                            <label for="field-kind" class="form-label">Used as</label>
                            <select id="field-kind" name="kind" class="form-select @error('kind') is-invalid @enderror" aria-describedby="field-kind-help">
                                @foreach (\App\Models\GlobalBlock::KINDS as $value => $label)
                                    <option value="{{ $value }}" @selected(old('kind', $item->kind ?? 'generic') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <div id="field-kind-help" class="form-text">“Sidebar” blocks can be shown next to news, events and other content (Settings → Sidebars, or per item).</div>
                            @error('kind')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <div class="col-xl-4">
            <section class="card pa-card mb-4" aria-labelledby="global-publish-heading">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h2 id="global-publish-heading" class="h6 mb-0">Publishing</h2>
                    @if ($editing)<x-admin.status-badge :status="$item->isPublished() ? 'published' : 'draft'" />@endif
                </div>
                <div class="card-body d-grid gap-2">
                    @if ($editing && $item->isPublished() && $item->has_unpublished_changes)
                        <p class="small mb-1"><i class="bi bi-exclamation-circle text-warning" aria-hidden="true"></i> Saved changes are not live yet.</p>
                    @endif
                    <button type="submit" form="global-form" class="btn btn-primary">{{ $editing ? 'Save' : 'Create' }}</button>
                    @if ($editing && $item->has_unpublished_changes)
                        <form method="POST" action="{{ route('admin.global-blocks.publish', $item) }}">
                            @csrf
                            <button type="submit" class="btn btn-success w-100">{{ $item->isPublished() ? 'Publish changes' : 'Publish' }}</button>
                        </form>
                        <p class="form-text mb-0">Save first: publishing uses the last saved version.</p>
                    @endif
                    @if ($editing)
                        <form method="POST" action="{{ route('admin.global-blocks.destroy', $item) }}" data-confirm="Delete “{{ $item->name }}”?">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-link text-danger p-0" @disabled($usages->isNotEmpty() || $roles !== [])><i class="bi bi-trash" aria-hidden="true"></i> Delete</button>
                        </form>
                    @endif
                </div>
            </section>

            @if ($editing)
                <section class="card pa-card" aria-labelledby="global-usage-heading">
                    <div class="card-header"><h2 id="global-usage-heading" class="h6 mb-0">Used in {{ trans_choice(':count place|:count places', $usages->count() + count($roles), ['count' => $usages->count() + count($roles)]) }}</h2></div>
                    @if ($roles !== [])
                        <ul class="list-group list-group-flush">
                            @foreach ($roles as $role)
                                <li class="list-group-item small"><i class="bi bi-check2-circle text-success me-1" aria-hidden="true"></i>{{ $role }}</li>
                            @endforeach
                        </ul>
                    @endif
                    @if ($usages->isEmpty() && $roles === [])
                        <div class="card-body small text-body-secondary">Not placed anywhere yet. It can be deleted.</div>
                    @else
                        <ul class="list-group list-group-flush">
                            @foreach ($usages as $usage)
                                <li class="list-group-item small">
                                    @if ($usage->owner instanceof \App\Models\Page)
                                        <a href="{{ route('admin.pages.edit', $usage->owner) }}">{{ $usage->owner->title }}</a> <span class="text-body-secondary">page</span>
                                    @elseif ($usage->owner instanceof \App\Models\BlockTemplate)
                                        <a href="{{ route('admin.block-templates.edit', $usage->owner) }}">{{ $usage->owner->name }}</a> <span class="text-body-secondary">template</span>
                                    @else
                                        {{ class_basename($usage->owner) }} #{{ $usage->owner_id }}
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            @endif
        </div>
    </div>

    <x-admin.block-builder :blocks="$blocks" form="global-form" context="global" heading="Global block content"
        :owner="$editing ? ['type' => 'global_block', 'id' => $item->id] : null" :autosave="$autosave" />
</x-admin.layout>
