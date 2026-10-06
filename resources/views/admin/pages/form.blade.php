@php
    $editing = $page->exists;
    $readonly = ! $canEdit;
    $seo = $page->seo;
    $tz = app(\App\Services\Settings\SettingsService::class)->get('site', 'timezone', 'UTC');
    $parentPath = $page->parent_id ? optional($parents->firstWhere('id', $page->parent_id))->path : null;
@endphp
<x-admin.layout :title="$editing ? $page->title : 'New page'">
    <x-admin.page-header :title="$editing ? $page->title : 'New page'" :subtitle="$editing ? '/'.$page->path : 'Pages start as drafts. Nothing is public until it is published.'">
        @if ($editing)
            <x-slot:actions>
                <a href="{{ route('admin.pages.index') }}" class="btn btn-link">All pages</a>
                @can('create', \App\Models\Page::class)
                    <a href="{{ route('admin.pages.create', ['parent' => $page->id]) }}" class="btn btn-outline-secondary"><i class="bi bi-plus-lg" aria-hidden="true"></i> Sub-page</a>
                @endcan
            </x-slot:actions>
        @endif
    </x-admin.page-header>

    @if ($readonly)
        <div class="alert alert-info pa-alert" role="status">
            <i class="bi bi-lock" aria-hidden="true"></i>
            @if ($page->status->value === 'archived')
                This page is archived. Restore it to edit.
            @else
                You can view this page but not edit it in its current state ({{ strtolower($page->status->label()) }}).
            @endif
        </div>
    @endif

    <div class="row g-4">
        <div class="col-xl-8">
            <form id="page-form" method="POST" action="{{ $editing ? route('admin.pages.update', $page) : route('admin.pages.store') }}" novalidate>
                @csrf
                @if ($editing) @method('PUT') <input type="hidden" name="lock_version" value="{{ $page->lock_version }}"> @endif
                @error('lock_version')<div class="alert alert-danger pa-alert" role="alert">{{ $message }}</div>@enderror

                <fieldset @disabled($readonly)>
                    <section class="card pa-card mb-4" aria-labelledby="content-heading">
                        <div class="card-header"><h2 id="content-heading" class="h6 mb-0">Content</h2></div>
                        <div class="card-body">
                            <x-admin.field name="title" label="Title" :value="$page->title" required data-slug-source="#field-slug" />
                            <div class="mb-3">
                                <label for="field-slug" class="form-label">URL</label>
                                <div class="input-group">
                                    <span class="input-group-text">/{{ $parentPath ? $parentPath.'/' : '' }}</span>
                                    <input id="field-slug" name="slug" class="form-control @error('slug') is-invalid @enderror" value="{{ old('slug', $page->slug) }}"
                                           pattern="[a-z0-9]+(-[a-z0-9]+)*" aria-describedby="slug-help" @if (! $editing) data-slug-auto @endif>
                                    @error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div id="slug-help" class="form-text">Lowercase letters, numbers and hyphens. Leave empty to create it from the title. Changing the URL of a published page adds an automatic redirect from the old address when you publish.</div>
                            </div>
                            <x-admin.field name="excerpt" label="Summary" type="textarea" :value="$page->excerpt"
                                help="Shown under the title and used as the default search-engine description." />
                            <p class="small text-body-secondary mb-0"><i class="bi bi-arrow-down-circle" aria-hidden="true"></i> Build the page content with blocks in the <a href="#builder-heading">Page content</a> section below.</p>
                        </div>
                    </section>

                    <section class="card pa-card mb-4" aria-labelledby="media-heading">
                        <div class="card-header"><h2 id="media-heading" class="h6 mb-0">Featured image</h2></div>
                        <div class="card-body">
                            <x-admin.media-picker name="featured_media_id" label="Featured image" :media="$page->featuredMedia" :disabled="$readonly" />
                        </div>
                    </section>

                    <section class="card pa-card mb-4" aria-labelledby="seo-heading">
                        <div class="card-header"><h2 id="seo-heading" class="h6 mb-0">Search engines &amp; social sharing</h2></div>
                        <div class="card-body">
                            <p class="small text-body-secondary">Leave empty to use the page title, summary and featured image automatically.</p>
                            <x-admin.field name="seo[title]" label="SEO title" :value="$seo?->title" help="Shown in the browser tab and search results." />
                            <x-admin.field name="seo[description]" label="Meta description" type="textarea" :value="$seo?->description" />
                            <x-admin.field name="seo[canonical_url]" label="Canonical URL" type="url" :value="$seo?->canonical_url" help="Only if this content's main address is elsewhere." />
                            <div class="d-flex flex-wrap gap-4 mb-3">
                                <input type="hidden" name="seo[robots_index]" value="0">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="seo[robots_index]" id="seo-index" value="1" @checked(old('seo.robots_index', $seo?->robots_index ?? true))>
                                    <label class="form-check-label" for="seo-index">Show in search engines</label>
                                </div>
                                <input type="hidden" name="seo[robots_follow]" value="0">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="seo[robots_follow]" id="seo-follow" value="1" @checked(old('seo.robots_follow', $seo?->robots_follow ?? true))>
                                    <label class="form-check-label" for="seo-follow">Let search engines follow links</label>
                                </div>
                            </div>
                            <x-admin.field name="seo[og_title]" label="Social title" :value="$seo?->og_title" />
                            <x-admin.field name="seo[og_description]" label="Social description" type="textarea" :value="$seo?->og_description" />
                            <x-admin.media-picker name="seo[og_image_media_id]" label="Social image" :media="$seo?->ogImage" :disabled="$readonly"
                                help="Used when the page is shared on Facebook, LinkedIn, WhatsApp… (1200×630 recommended)." />
                        </div>
                    </section>

                    <section class="card pa-card mb-4" aria-labelledby="attributes-heading">
                        <div class="card-header"><h2 id="attributes-heading" class="h6 mb-0">Page settings</h2></div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="field-parent" class="form-label">Parent page</label>
                                <select id="field-parent" name="parent_id" class="form-select @error('parent_id') is-invalid @enderror">
                                    <option value="">— None (top level) —</option>
                                    @foreach ($parents as $parent)
                                        <option value="{{ $parent->id }}" @selected((int) old('parent_id', $page->parent_id) === $parent->id)>{{ str_repeat('— ', substr_count($parent->path, '/')) }}{{ $parent->title }} (/{{ $parent->path }})</option>
                                    @endforeach
                                </select>
                                @error('parent_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="mb-3">
                                <label for="field-template" class="form-label">Layout</label>
                                <select id="field-template" name="template" class="form-select">
                                    @foreach ($templates as $value => $label)
                                        <option value="{{ $value }}" @selected(old('template', $page->template ?? 'default') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-check mb-0">
                                <input type="hidden" name="show_title" value="0">
                                <input type="checkbox" id="field-show-title" name="show_title" value="1" class="form-check-input" aria-describedby="field-show-title-help"
                                    @checked((bool) old('show_title', $page->show_title ?? true))>
                                <label for="field-show-title" class="form-check-label">Show page title</label>
                                <div id="field-show-title-help" class="form-text">Shows the title and summary above the page content. When off, the title stays available to screen readers and search engines. A Heading 1 block in the content always replaces it.</div>
                            </div>
                        </div>
                    </section>
                </fieldset>
            </form>
        </div>

        <div class="col-xl-4">
            <aside class="pa-sticky" aria-label="Publishing">
                <section class="card pa-card mb-4" aria-labelledby="publish-heading">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h2 id="publish-heading" class="h6 mb-0">Publishing</h2>
                        @if ($editing)<x-admin.content-status :item="$page" />@endif
                    </div>
                    <div class="card-body">
                        @if ($editing)
                            <dl class="small mb-3 pa-meta">
                                <dt>Live version</dt>
                                <dd>
                                    @if ($page->isLive())
                                        <a href="{{ url($page->publicUrl()) }}" target="_blank" rel="noopener">{{ $page->publicUrl() }}<span class="visually-hidden"> (opens in new tab)</span></a>
                                        · {{ $page->published_at?->timezone($tz)->format('j M Y, H:i') }}
                                    @else
                                        Not published
                                    @endif
                                </dd>
                                @if ($page->publish_at)
                                    <dt>Scheduled</dt>
                                    <dd>{{ $page->publish_at->timezone($tz)->format('j M Y, H:i') }} ({{ $tz }})</dd>
                                @endif
                                <dt>Author</dt>
                                <dd>{{ $page->author?->name ?? '—' }}</dd>
                            </dl>
                        @endif

                        <div class="d-grid gap-2">
                            @if ($canEdit)
                                <button type="submit" form="page-form" class="btn btn-primary">{{ $editing ? 'Save draft' : 'Create draft' }}</button>
                            @endif
                            @if ($editing)
                                <a href="{{ route('admin.pages.preview', $page) }}" class="btn btn-outline-secondary" target="_blank" rel="noopener">
                                    <i class="bi bi-eye" aria-hidden="true"></i> Preview<span class="visually-hidden"> (opens in new tab)</span>
                                </a>
                            @endif
                        </div>

                        @if ($editing && $actions !== [])
                            <hr>
                            <p class="small fw-semibold mb-2">Workflow</p>
                            <div class="d-grid gap-2">
                                @foreach ($actions as $action)
                                    @continue(in_array($action, [\App\Enums\WorkflowAction::Schedule, \App\Enums\WorkflowAction::RequestChanges], true))
                                    <form method="POST" action="{{ route('admin.pages.workflow', $page) }}"
                                          @if (in_array($action, [\App\Enums\WorkflowAction::Unpublish, \App\Enums\WorkflowAction::Archive], true)) data-confirm="{{ $action->label() }} “{{ $page->title }}”? It will no longer be visible on the website." @endif>
                                        @csrf
                                        <input type="hidden" name="action" value="{{ $action->value }}">
                                        <button type="submit" @class([
                                            'btn w-100',
                                            'btn-success' => $action === \App\Enums\WorkflowAction::Publish,
                                            'btn-outline-danger' => in_array($action, [\App\Enums\WorkflowAction::Unpublish, \App\Enums\WorkflowAction::Archive], true),
                                            'btn-outline-primary' => ! in_array($action, [\App\Enums\WorkflowAction::Publish, \App\Enums\WorkflowAction::Unpublish, \App\Enums\WorkflowAction::Archive], true),
                                        ])>{{ $action === \App\Enums\WorkflowAction::Publish && $page->isLive() ? 'Publish changes' : $action->label() }}</button>
                                    </form>
                                @endforeach

                                @if (in_array(\App\Enums\WorkflowAction::RequestChanges, $actions, true))
                                    <form method="POST" action="{{ route('admin.pages.workflow', $page) }}" class="border rounded p-2">
                                        @csrf
                                        <input type="hidden" name="action" value="request_changes">
                                        <label for="rc-note" class="form-label small mb-1">Note for the author</label>
                                        <textarea id="rc-note" name="note" rows="2" class="form-control form-control-sm mb-2" maxlength="1000"></textarea>
                                        <button type="submit" class="btn btn-sm btn-outline-secondary w-100">Request changes</button>
                                    </form>
                                @endif

                                @if (in_array(\App\Enums\WorkflowAction::Schedule, $actions, true))
                                    <form method="POST" action="{{ route('admin.pages.workflow', $page) }}" class="border rounded p-2">
                                        @csrf
                                        <input type="hidden" name="action" value="schedule">
                                        <label for="schedule-at" class="form-label small mb-1">Publish automatically at ({{ $tz }})</label>
                                        <input id="schedule-at" type="datetime-local" name="publish_at" class="form-control form-control-sm mb-2 @error('publish_at') is-invalid @enderror" required>
                                        @error('publish_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        <button type="submit" class="btn btn-sm btn-outline-primary w-100">Schedule</button>
                                    </form>
                                @endif
                            </div>
                        @endif
                        @error('action')<div class="alert alert-danger pa-alert mt-3 mb-0" role="alert">{{ $message }}</div>@enderror
                    </div>
                </section>

                @if ($editing)
                    <section class="card pa-card mb-4" aria-labelledby="revisions-heading">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h2 id="revisions-heading" class="h6 mb-0">Revisions</h2>
                            <a href="{{ route('admin.pages.revisions', $page) }}" class="small">All &amp; compare</a>
                        </div>
                        <ul class="list-group list-group-flush small">
                            @foreach ($revisions as $revision)
                                <li class="list-group-item">
                                    <div class="d-flex justify-content-between gap-2">
                                        <span><strong>#{{ $revision->number }}</strong> {{ $revision->kind->label() }}</span>
                                        <time datetime="{{ $revision->created_at->toIso8601String() }}" class="text-body-secondary">{{ $revision->created_at->diffForHumans() }}</time>
                                    </div>
                                    <div class="text-body-secondary">{{ $revision->author?->name ?? 'System' }}@if ($revision->summary) · {{ $revision->summary }}@endif</div>
                                </li>
                            @endforeach
                        </ul>
                    </section>

                    @can('delete', $page)
                        <form method="POST" action="{{ route('admin.pages.destroy', $page) }}" data-confirm="Delete “{{ $page->title }}”? @if ($page->isLive()) It will be removed from the website. @endif">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-link text-danger p-0"><i class="bi bi-trash" aria-hidden="true"></i> Delete page</button>
                        </form>
                    @endcan
                @endif
            </aside>
        </div>
    </div>

    {{-- Block Builder (React island). Its hidden input belongs to the page form, so
         "Save draft" stores fields and blocks together. --}}
    @php
        $blockErrors = collect($errors->getMessages())->filter(fn ($m, $key) => str_starts_with($key, 'blocks'))->all();
        $oldBlocks = old('blocks');
        $builderData = [
            'blocks' => $oldBlocks !== null ? (json_decode($oldBlocks, true) ?? []) : $blocks,
            'errors' => $blockErrors,
            'readonly' => $readonly,
            'endpoints' => [
                'definitions' => route('admin.api.blocks.definitions'),
                'resolve' => route('admin.api.blocks.resolve'),
                'linkTargets' => route('admin.api.link-targets'),
                'media' => route('admin.api.media.index'),
                'mediaUpload' => route('admin.api.media.store'),
                'previewFrame' => route('preview.builder', [], false),
            ],
        ];
    @endphp
    <section class="card pa-card mt-4" aria-labelledby="builder-heading">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h2 id="builder-heading" class="h6 mb-0">Page content</h2>
            @if ($blockErrors)
                <span class="pa-badge pa-badge--danger">{{ count($blockErrors) }} block {{ \Illuminate\Support\Str::plural('error', count($blockErrors)) }}</span>
            @endif
        </div>
        <div class="card-body p-0">
            <input type="hidden" id="blocks-input" name="blocks" form="page-form" value="{{ $oldBlocks ?? json_encode($blocks) }}">
            <div id="page-builder">
                <p class="p-4 mb-0 text-body-secondary"><span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Loading the block builder…</p>
            </div>
        </div>
    </section>
    <script id="page-builder-data" type="application/json">{!! json_encode($builderData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) !!}</script>
    @push('islands')
        @viteReactRefresh
        @vite('resources/js/admin/islands/page-builder.jsx')
    @endpush
</x-admin.layout>
