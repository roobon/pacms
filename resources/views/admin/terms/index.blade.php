<x-admin.layout :title="$definition['label']">
    <x-admin.page-header :title="$definition['label']" subtitle="Categories and tags are shared across the CMS. Items using a term must be reassigned before it can be deleted." />

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card pa-card">
                @if ($terms->isEmpty())
                    <x-admin.empty-state icon="bi-tags" :title="'No '.strtolower($definition['label']).' yet'" />
                @else
                    <ul class="list-group list-group-flush">
                        @foreach ($terms as $term)
                            <li class="list-group-item">
                                <details>
                                    <summary class="d-flex justify-content-between align-items-center gap-2">
                                        <span>
                                            @if ($term->parent_id)<span class="text-body-secondary">{{ optional($terms->firstWhere('id', $term->parent_id))->name }} ›</span>@endif
                                            <strong>{{ $term->name }}</strong> <code class="small">{{ $term->slug }}</code>
                                        </span>
                                        <span class="small text-body-secondary">{{ (int) ($usage[$term->id] ?? 0) }} item(s)</span>
                                    </summary>
                                    <form method="POST" action="{{ route('admin.terms.update', [$taxonomy, $term]) }}" class="row g-2 mt-2 align-items-end">
                                        @csrf @method('PUT')
                                        <div class="col-md-4"><label class="form-label small" for="name-{{ $term->id }}">Name</label><input id="name-{{ $term->id }}" name="name" value="{{ $term->name }}" class="form-control form-control-sm" required maxlength="191"></div>
                                        <div class="col-md-3"><label class="form-label small" for="slug-{{ $term->id }}">Slug</label><input id="slug-{{ $term->id }}" name="slug" value="{{ $term->slug }}" class="form-control form-control-sm"></div>
                                        @if ($definition['hierarchical'])
                                            <div class="col-md-3">
                                                <label class="form-label small" for="parent-{{ $term->id }}">Parent</label>
                                                <select id="parent-{{ $term->id }}" name="parent_id" class="form-select form-select-sm">
                                                    <option value="">—</option>
                                                    @foreach ($terms->where('id', '!=', $term->id) as $option)
                                                        <option value="{{ $option->id }}" @selected($term->parent_id === $option->id)>{{ $option->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        @endif
                                        <div class="col-md-2"><button type="submit" class="btn btn-sm btn-outline-primary w-100">Save</button></div>
                                    </form>
                                    <form method="POST" action="{{ route('admin.terms.destroy', [$taxonomy, $term]) }}" class="mt-2" data-confirm="Delete “{{ $term->name }}”?">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-link btn-sm text-danger p-0">Delete</button>
                                    </form>
                                </details>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
        <div class="col-lg-5">
            <form method="POST" action="{{ route('admin.terms.store', $taxonomy) }}" class="card pa-card">
                @csrf
                <div class="card-header"><h2 class="h6 mb-0">Add {{ strtolower($definition['singular']) }}</h2></div>
                <div class="card-body">
                    <x-admin.field name="name" label="Name" required />
                    <x-admin.field name="slug" label="Slug" help="Optional — generated from the name." />
                    @if ($definition['hierarchical'] && $terms->isNotEmpty())
                        <div class="mb-3">
                            <label for="new-parent" class="form-label">Parent</label>
                            <select id="new-parent" name="parent_id" class="form-select">
                                <option value="">—</option>
                                @foreach ($terms as $option)
                                    <option value="{{ $option->id }}">{{ $option->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <button type="submit" class="btn btn-primary">Add</button>
                </div>
            </form>
        </div>
    </div>
</x-admin.layout>
