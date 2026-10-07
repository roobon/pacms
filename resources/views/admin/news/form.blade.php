@php($editing = $item->exists)
@php($canEdit = $editing ? auth()->user()->can('update', $item) && ($item->status->value !== 'published' || auth()->user()->can('news.publish')) : true)
<x-admin.layout :title="$editing ? $item->title : 'New news item'">
    <x-admin.page-header :title="$editing ? $item->title : 'New news item'" :subtitle="$editing ? '/news/'.$item->slug : 'News starts as a draft.'">
        <x-slot:actions><a href="{{ route('admin.news.index') }}" class="btn btn-link">All news</a></x-slot:actions>
    </x-admin.page-header>

    <div class="row g-4">
        <div class="col-xl-8">
            <form id="news-form" method="POST" action="{{ $editing ? route('admin.news.update', $item) : route('admin.news.store') }}" novalidate>
                @csrf
                @if ($editing) @method('PUT') @endif
                <fieldset class="card pa-card" @disabled(! $canEdit)>
                    <div class="card-body">
                        <x-admin.field name="title" label="Title" :value="$item->title" required data-slug-source="#field-slug" />
                        <x-admin.field name="slug" label="URL slug" :value="$item->slug" help="Optional — generated from the title." :data-slug-auto="$editing ? null : true" />
                        <x-admin.field name="excerpt" label="Summary" type="textarea" :value="$item->excerpt" data-summary-editor help="Shown on news cards and as the search-engine description (formatting is removed there)." />
                        <x-admin.media-picker name="featured_media_id" label="Image" :media="$item->featuredMedia" :disabled="! $canEdit" />
                        @if ($categories->isNotEmpty())
                            @php($selected = old('categories', $item->exists ? $item->terms->pluck('id')->all() : []))
                            <fieldset class="mb-3">
                                <legend class="form-label fs-6">Categories</legend>
                                @foreach ($categories as $category)
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="checkbox" name="categories[]" value="{{ $category->id }}" id="cat-{{ $category->id }}" @checked(in_array($category->id, array_map('intval', $selected), true))>
                                        <label class="form-check-label" for="cat-{{ $category->id }}">{{ $category->name }}</label>
                                    </div>
                                @endforeach
                            </fieldset>
                        @endif
                        <div class="form-check">
                            <input type="hidden" name="featured" value="0">
                            <input class="form-check-input" type="checkbox" name="featured" value="1" id="featured" @checked(old('featured', $item->featured))>
                            <label class="form-check-label" for="featured">Featured</label>
                        </div>
                    </div>
                </fieldset>
            </form>
        </div>
        <div class="col-xl-4">
            <section class="card pa-card" aria-labelledby="news-publish-heading">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h2 id="news-publish-heading" class="h6 mb-0">Publishing</h2>
                    @if ($editing)<x-admin.status-badge :status="$item->status->value" />@endif
                </div>
                <div class="card-body d-grid gap-2">
                    @if ($canEdit)
                        <button type="submit" form="news-form" class="btn btn-primary">{{ $editing ? 'Save' : 'Create draft' }}</button>
                    @endif
                    @if ($editing && auth()->user()->can('news.publish'))
                        <form method="POST" action="{{ route('admin.news.publish', $item) }}">
                            @csrf
                            @if ($item->isPublished())
                                <input type="hidden" name="unpublish" value="1">
                                <button type="submit" class="btn btn-outline-danger w-100">Unpublish</button>
                            @else
                                <button type="submit" class="btn btn-success w-100">Publish</button>
                            @endif
                        </form>
                    @endif
                    @if ($editing && $item->isPublished())
                        <a href="{{ url($item->url()) }}" target="_blank" rel="noopener" class="btn btn-link">View on site<span class="visually-hidden"> (opens in new tab)</span></a>
                    @endif
                    @if ($editing)
                        @can('delete', $item)
                            <form method="POST" action="{{ route('admin.news.destroy', $item) }}" data-confirm="Delete “{{ $item->title }}”?">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-link text-danger p-0"><i class="bi bi-trash" aria-hidden="true"></i> Delete</button>
                            </form>
                        @endcan
                    @endif
                </div>
            </section>
        </div>
    </div>
    @push('islands')
        @viteReactRefresh
        @vite('resources/js/admin/islands/summary-editor.jsx')
    @endpush
</x-admin.layout>
