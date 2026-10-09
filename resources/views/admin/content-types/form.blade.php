@php
    $editing = $contentType->exists;
    $heading = $editing ? $contentType->label : 'New content type';
    $fieldErrors = (object) collect($errors->getMessages())->filter(fn ($m, $k) => str_starts_with($k, 'fields'))->all();
    $config = [
        'fields' => old('fields') !== null ? (json_decode((string) old('fields'), true) ?? []) : ($contentType->fields ?? []),
        'display' => (object) (old('display') ?? ($contentType->display ?? [])),
        'types' => $fieldTypes,
        'displays' => $displays,
        'sectionOnly' => $sectionOnly,
        'errors' => $fieldErrors,
        'hasItems' => $editing && $contentType->items_count > 0,
    ];
@endphp
<x-admin.layout :title="$heading">
    <x-admin.page-header :title="$heading" :subtitle="$editing ? '/'.$contentType->route_prefix.' · '.$contentType->items_count.' '.Str::plural('item', $contentType->items_count) : 'Name the type, choose its address and build its fields. Items get a title, summary, text, image, SEO and builder content automatically.'">
        <x-slot:actions><a href="{{ route('admin.content-types.index') }}" class="btn btn-link">All content types</a></x-slot:actions>
    </x-admin.page-header>

    <div class="row g-4">
        <div class="col-xl-8">
            <form id="content-type-form" method="POST" action="{{ $editing ? route('admin.content-types.update', $contentType) : route('admin.content-types.store') }}" novalidate>
                @csrf
                @if ($editing) @method('PUT') @endif

                <section class="card pa-card mb-4" aria-labelledby="names-heading">
                    <div class="card-header"><h2 id="names-heading" class="h6 mb-0">Name and address</h2></div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6"><x-admin.field name="label" label="Name (plural)" :value="$contentType->label" required help="e.g. Success stories. Shown in the menu and as the listing title." /></div>
                            <div class="col-md-6"><x-admin.field name="singular" label="One item" :value="$contentType->singular" help="e.g. success story. Used in buttons such as “New success story”." /></div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <label for="field-route_prefix" class="form-label">Address</label>
                                <div class="input-group has-validation">
                                    <span class="input-group-text">/</span>
                                    <input id="field-route_prefix" name="route_prefix" class="form-control @error('route_prefix') is-invalid @enderror" value="{{ old('route_prefix', $contentType->route_prefix) }}"
                                           pattern="[a-z0-9]+(-[a-z0-9]+)*" aria-describedby="prefix-help">
                                    @error('route_prefix')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div id="prefix-help" class="form-text">Items appear at /address/item-name. Leave empty to make it from the name.</div>
                            </div>
                            <div class="col-md-6">
                                <x-admin.field name="icon" label="Menu icon" :value="$contentType->icon ?: 'bi-collection'" help="A Bootstrap Icons name, e.g. bi-star, bi-briefcase, bi-trophy." />
                            </div>
                        </div>
                    </div>
                </section>

                <section class="card pa-card mb-4" aria-labelledby="workflow-heading">
                    <div class="card-header"><h2 id="workflow-heading" class="h6 mb-0">Publishing and options</h2></div>
                    <div class="card-body">
                        @if (! $editing)
                            <fieldset class="mb-3">
                                <legend class="form-label fs-6">How items are published</legend>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="workflow" id="workflow-editorial" value="editorial" @checked(old('workflow', 'editorial') === 'editorial')>
                                    <label class="form-check-label" for="workflow-editorial">Editorial workflow, like News: drafts, review, approval, scheduling</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="workflow" id="workflow-managed" value="managed" @checked(old('workflow') === 'managed')>
                                    <label class="form-check-label" for="workflow-managed">Simply active or inactive, like Team (one permission, a display order)</label>
                                </div>
                                <div class="form-text">This cannot be changed later.</div>
                            </fieldset>
                        @else
                            <p class="small mb-3"><strong>Publishing:</strong> {{ $contentType->workflow === 'managed' ? 'active or inactive, with a display order' : 'editorial workflow (drafts, review, approval, scheduling)' }}.</p>
                        @endif
                        @foreach ([
                            'has_archive' => ['A listing page at /'.($contentType->route_prefix ?: 'address'), 'Turn off if items are only shown in blocks on your pages.', $contentType->has_archive],
                            'searchable' => ['Include items in the site search', null, $contentType->searchable],
                        ] as $name => [$label, $help, $current])
                            <div class="form-check mb-2">
                                <input type="hidden" name="{{ $name }}" value="0">
                                <input class="form-check-input" type="checkbox" name="{{ $name }}" value="1" id="field-{{ $name }}" @checked(old($name, $editing ? $current : true))>
                                <label class="form-check-label" for="field-{{ $name }}">{{ $label }}</label>
                                @if ($help)<div class="form-text mt-0">{{ $help }}</div>@endif
                            </div>
                        @endforeach
                        @if ($editing)
                            <div class="form-check mb-0">
                                <input type="hidden" name="is_active" value="0">
                                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="field-is_active" @checked(old('is_active', $contentType->is_active))>
                                <label class="form-check-label" for="field-is_active">Active</label>
                                <div class="form-text mt-0">A disabled type disappears from the menu and the website; its items are kept.</div>
                            </div>
                        @endif
                    </div>
                </section>

                <section class="card pa-card mb-4" aria-labelledby="fields-heading">
                    <div class="card-header"><h2 id="fields-heading" class="h6 mb-0">Fields</h2></div>
                    <div class="card-body">
                        <p class="small text-body-secondary">Every item already has a title, URL, summary, main text, image, SEO settings and optional builder content. Add the fields that are specific to this type, and choose where each one appears on the item's page.</p>
                        @error('fields')<div class="alert alert-danger pa-alert" role="alert">{{ $message }}</div>@enderror
                        <div data-content-type-fields data-config="{{ json_encode($config) }}">
                            {{-- Without JavaScript the current fields are kept as they are. --}}
                            <input type="hidden" name="fields" value="{{ json_encode($config['fields']) }}">
                            <p class="small text-body-secondary mb-0">{{ count($config['fields']) }} fields.</p>
                        </div>
                    </div>
                </section>
            </form>
        </div>

        <div class="col-xl-4">
            <aside class="pa-sticky" aria-label="Save">
                <section class="card pa-card mb-4">
                    <div class="card-body d-grid gap-2">
                        <button type="submit" form="content-type-form" class="btn btn-primary">{{ $editing ? 'Save changes' : 'Create content type' }}</button>
                        @if ($editing && $adminType)
                            <a href="{{ $adminType->adminUrl() }}" class="btn btn-outline-secondary"><i class="bi {{ $contentType->icon }}" aria-hidden="true"></i> Open {{ strtolower($contentType->label) }}</a>
                        @endif
                    </div>
                </section>
                @if ($editing)
                    <section class="card pa-card mb-4" aria-labelledby="permissions-heading">
                        <div class="card-header"><h2 id="permissions-heading" class="h6 mb-0">Permissions</h2></div>
                        <div class="card-body small">
                            <p>Who may work with {{ strtolower($contentType->label) }} is set in <a href="{{ route('admin.roles.index') }}">Roles &amp; Permissions</a>, in the group “{{ $contentType->label }} (content type)”.</p>
                            <p class="mb-0 text-body-secondary">New types start like News: editors manage everything; authors and contributors write and submit.</p>
                        </div>
                    </section>
                    <section class="card pa-card mb-4" aria-labelledby="delete-heading">
                        <div class="card-header"><h2 id="delete-heading" class="h6 mb-0">Delete</h2></div>
                        <div class="card-body small">
                            @if ($contentType->items_count > 0)
                                <p class="mb-0">A type with items cannot be deleted. Delete its {{ $contentType->items_count }} {{ Str::plural('item', $contentType->items_count) }} first, or disable the type above.</p>
                            @else
                                <form method="POST" action="{{ route('admin.content-types.destroy', $contentType) }}" data-confirm="Delete the content type “{{ $contentType->label }}” and its permissions?">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete content type</button>
                                </form>
                            @endif
                            @error('type')<div class="alert alert-danger pa-alert mt-2 mb-0" role="alert">{{ $message }}</div>@enderror
                        </div>
                    </section>
                @endif
            </aside>
        </div>
    </div>

    @push('islands')
        @viteReactRefresh
        @vite('resources/js/admin/islands/content-type-fields.jsx')
    @endpush
</x-admin.layout>
