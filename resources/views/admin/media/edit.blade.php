<x-admin.layout :title="$item->original_name">
    <x-admin.page-header :title="$item->original_name" :subtitle="$item->kind->label().' · '.$item->humanSize().($item->width ? ' · '.$item->width.'×'.$item->height.' px' : '')">
        <x-slot:actions>
            <a href="{{ route('admin.media.index') }}" class="btn btn-link">Library</a>
            @if ($item->isPublic())
                <a href="{{ $item->url() }}" class="btn btn-outline-secondary" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right" aria-hidden="true"></i> Open file<span class="visually-hidden"> (opens in new tab)</span></a>
            @else
                <a href="{{ route('admin.media.download', $item) }}" class="btn btn-outline-secondary"><i class="bi bi-download" aria-hidden="true"></i> Download</a>
            @endif
        </x-slot:actions>
    </x-admin.page-header>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card pa-card mb-4">
                {{-- Preview: public files from their URL, private ones through the staff-only file route. --}}
                @php($fileUrl = $item->isPublic() ? $item->url() : route('admin.media.file', $item))
                @if ($item->isImage())
                    <div class="pa-media-tile__thumb" style="aspect-ratio: auto; min-height: 12rem">
                        <img src="{{ $item->isPublic() ? $item->thumbnailUrl(960) : $fileUrl }}" alt="{{ $item->alt }}" style="object-fit: contain; max-height: 28rem">
                    </div>
                @elseif ($item->kind === \App\Enums\MediaKind::Video)
                    <video class="pa-media-preview" src="{{ $fileUrl }}" controls preload="metadata"></video>
                @elseif ($item->kind === \App\Enums\MediaKind::Audio)
                    <div class="p-3"><audio class="w-100" src="{{ $fileUrl }}" controls preload="metadata"></audio></div>
                @elseif ($item->extension === 'pdf')
                    <iframe class="pa-media-preview" src="{{ route('admin.media.file', $item) }}" style="height: 28rem" title="Preview of {{ $item->original_name }}"></iframe>
                    <p class="small px-3 pt-2 mb-0">Preview not showing? <a href="{{ route('admin.media.file', $item) }}" target="_blank" rel="noopener">Open the PDF<span class="visually-hidden"> (opens in new tab)</span></a> or <a href="{{ route('admin.media.download', $item) }}">download it</a>.</p>
                @else
                    <div class="pa-media-tile__thumb" style="aspect-ratio: auto; min-height: 12rem"><i class="bi {{ $item->kind->icon() }}" aria-hidden="true"></i></div>
                @endif
                <div class="card-body small">
                    <dl class="pa-meta mb-0">
                        <dt>Type</dt><dd>{{ $item->mime_type }}</dd>
                        <dt>Uploaded</dt><dd>{{ $item->created_at->format('j M Y, H:i') }} by {{ $item->uploader?->name ?? '—' }}</dd>
                        <dt>Visibility</dt><dd>{{ $item->isPublic() ? 'Public' : 'Private (staff only)' }}</dd>
                        @if ($item->isImage())
                            <dt>Sizes</dt><dd>{{ $item->variantUrls() ? count($item->variantUrls()).' optimised WebP sizes' : 'Being generated…' }}</dd>
                        @endif
                        @if ($item->isPublic())
                            <dt>URL</dt><dd><code class="user-select-all text-break">{{ url((string) $item->url()) }}</code></dd>
                        @endif
                    </dl>
                </div>
            </div>

            <section class="card pa-card mb-4" aria-labelledby="usage-heading">
                <div class="card-header"><h2 id="usage-heading" class="h6 mb-0">Where it is used</h2></div>
                @if ($usages->isEmpty())
                    <div class="card-body small text-body-secondary">Not used anywhere yet.</div>
                @else
                    <ul class="list-group list-group-flush small">
                        @foreach ($usages as $usage)
                            <li class="list-group-item d-flex justify-content-between">
                                @if ($usage->owner instanceof \App\Models\Page)
                                    <a href="{{ route('admin.pages.edit', $usage->owner) }}">{{ $usage->owner->title }}</a>
                                @elseif ($usage->owner instanceof \App\Models\News)
                                    <a href="{{ route('admin.news.edit', $usage->owner) }}">{{ $usage->owner->title }}</a> <span class="text-body-secondary">news</span>
                                @elseif ($usage->owner instanceof \App\Models\GlobalBlock)
                                    <a href="{{ route('admin.global-blocks.edit', $usage->owner) }}">{{ $usage->owner->name }}</a> <span class="text-body-secondary">global block</span>
                                @elseif ($usage->owner instanceof \App\Models\BlockTemplate)
                                    <a href="{{ route('admin.block-templates.edit', $usage->owner) }}">{{ $usage->owner->name }}</a> <span class="text-body-secondary">template</span>
                                @elseif ($usage->owner instanceof \App\Models\BlockType)
                                    <a href="{{ route('admin.block-types.edit', $usage->owner) }}">{{ $usage->owner->name }}</a> <span class="text-body-secondary">custom block</span>
                                @else
                                    <span>{{ class_basename($usage->owner) }} #{{ $usage->owner_id }}</span>
                                @endif
                                <span class="text-body-secondary">{{ str_replace('_', ' ', $usage->context) }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            @can('media.update')
                <section class="card pa-card mb-4" aria-labelledby="visibility-heading">
                    <div class="card-header"><h2 id="visibility-heading" class="h6 mb-0">Visibility</h2></div>
                    <div class="card-body small">
                        @if ($item->isPublic())
                            <p>Public: anyone with the address can open it.</p>
                            <form method="POST" action="{{ route('admin.media.visibility', $item) }}">
                                @csrf <input type="hidden" name="private" value="1">
                                <button type="submit" class="btn btn-sm btn-outline-secondary" @disabled($usages->isNotEmpty())><i class="bi bi-lock" aria-hidden="true"></i> Make private</button>
                                @if ($usages->isNotEmpty())<div class="form-text">Used on the site, so it must stay public.</div>@endif
                            </form>
                        @else
                            <p>Private: only signed-in staff can open it; it is never served from the public storage.</p>
                            <form method="POST" action="{{ route('admin.media.visibility', $item) }}">
                                @csrf <input type="hidden" name="private" value="0">
                                <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="bi bi-unlock" aria-hidden="true"></i> Make public</button>
                            </form>
                        @endif
                    </div>
                </section>

                <section class="card pa-card mb-4" aria-labelledby="replace-heading">
                    <div class="card-header"><h2 id="replace-heading" class="h6 mb-0">Replace file</h2></div>
                    <form method="POST" action="{{ route('admin.media.replace', $item) }}" enctype="multipart/form-data" class="card-body">
                        @csrf
                        <p class="small text-body-secondary">Keeps this item and every place it is used; only the file changes. Must be the same type ({{ strtolower($item->kind->label()) }}).</p>
                        <label for="replace-file" class="visually-hidden">New file</label>
                        <input id="replace-file" type="file" name="file" class="form-control mb-2 @error('file') is-invalid @enderror" required>
                        @error('file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <button type="submit" class="btn btn-outline-primary btn-sm">Replace</button>
                    </form>
                </section>
            @endcan
        </div>

        <div class="col-lg-7">
            <form method="POST" action="{{ route('admin.media.update', $item) }}" class="card pa-card mb-4">
                @csrf @method('PUT')
                <fieldset class="card-body" @cannot('media.update') disabled @endcannot>
                    <legend class="h6">Details</legend>
                    @if ($item->isImage())
                        <x-admin.field name="alt" label="Alternative text" :value="$item->alt"
                            help="Describe what the image shows for people using screen readers. Required unless the image is purely decorative." />
                        <div class="form-check mb-3">
                            <input type="hidden" name="is_decorative" value="0">
                            <input class="form-check-input" type="checkbox" name="is_decorative" value="1" id="is-decorative" @checked(old('is_decorative', $item->is_decorative))>
                            <label class="form-check-label" for="is-decorative">Decorative image (no alternative text needed)</label>
                        </div>
                    @endif
                    <x-admin.field name="caption" label="Caption" type="textarea" :value="$item->caption" />
                    <x-admin.field name="credit" label="Credit / photographer" :value="$item->credit" />
                    <x-admin.field name="description" label="Description (internal)" type="textarea" :value="$item->description" />

                    @if ($item->isImage())
                        <fieldset class="mb-3">
                            <legend class="form-label fs-6">Focal point</legend>
                            <p class="form-text mt-0">Where to keep the focus when the image is cropped (0 = left/top, 1 = right/bottom).</p>
                            <div class="row g-2">
                                <div class="col-6"><label for="focal-x" class="form-label small">Horizontal</label><input id="focal-x" type="number" step="0.05" min="0" max="1" name="focal_x" value="{{ old('focal_x', $item->focal_point['x'] ?? 0.5) }}" class="form-control"></div>
                                <div class="col-6"><label for="focal-y" class="form-label small">Vertical</label><input id="focal-y" type="number" step="0.05" min="0" max="1" name="focal_y" value="{{ old('focal_y', $item->focal_point['y'] ?? 0.5) }}" class="form-control"></div>
                            </div>
                        </fieldset>
                    @endif

                    @php($selected = $item->terms->pluck('id')->all())
                    @if ($categories->isNotEmpty())
                        <fieldset class="mb-3">
                            <legend class="form-label fs-6">Categories</legend>
                            @foreach ($categories as $category)
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" name="categories[]" value="{{ $category->id }}" id="cat-{{ $category->id }}" @checked(in_array($category->id, $selected, true))>
                                    <label class="form-check-label" for="cat-{{ $category->id }}">{{ $category->name }}</label>
                                </div>
                            @endforeach
                        </fieldset>
                    @endif
                    @if ($tags->isNotEmpty())
                        <fieldset class="mb-3">
                            <legend class="form-label fs-6">Tags</legend>
                            @foreach ($tags as $tag)
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" name="tags[]" value="{{ $tag->id }}" id="tag-{{ $tag->id }}" @checked(in_array($tag->id, $selected, true))>
                                    <label class="form-check-label" for="tag-{{ $tag->id }}">{{ $tag->name }}</label>
                                </div>
                            @endforeach
                        </fieldset>
                    @endif

                    <button type="submit" class="btn btn-primary">Save details</button>
                </fieldset>
            </form>

            @can('media.delete')
                <form method="POST" action="{{ route('admin.media.destroy', $item) }}"
                      data-confirm="Permanently delete “{{ $item->original_name }}”? This cannot be undone.">
                    @csrf @method('DELETE')
                    @if ($usages->isNotEmpty())
                        @can('media.force_delete')
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="force" value="1" id="force-delete">
                                <label class="form-check-label small" for="force-delete">Delete even though it is used in {{ $usages->count() }} place(s)</label>
                            </div>
                        @endcan
                    @endif
                    @error('media')<div class="alert alert-danger pa-alert small">{{ $message }}</div>@enderror
                    <button type="submit" class="btn btn-link text-danger p-0"><i class="bi bi-trash" aria-hidden="true"></i> Delete permanently</button>
                </form>
            @endcan
        </div>
    </div>
</x-admin.layout>
