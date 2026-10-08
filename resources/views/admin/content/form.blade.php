@php
    use App\Enums\WorkflowAction;
    $editing = $item->exists;
    $readonly = ! $canEdit;
    $seo = $item->seo;
    $key = $type->key();
    $heading = $editing ? $item->title : 'New '.$type->singular();
    $labels = $type->labels();
    $sections = collect($type->fields())->groupBy(fn ($field) => $field['section'] ?? 'Details', preserveKeys: true);
@endphp
<x-admin.layout :title="$heading">
    <x-admin.page-header :title="$heading" :subtitle="$editing ? $item->url() : ucfirst($type->singular()).' starts as a draft. Nothing is public until it is published.'">
        <x-slot:actions><a href="{{ route("admin.{$key}.index") }}" class="btn btn-link">All {{ strtolower($type->label()) }}</a></x-slot:actions>
    </x-admin.page-header>

    @if ($readonly)
        <div class="alert alert-info pa-alert" role="status">
            <i class="bi bi-lock" aria-hidden="true"></i>
            @if ($item->status->value === 'archived')
                This {{ $type->singular() }} is archived. Restore it to edit.
            @elseif ($item->status->value === 'published')
                This {{ $type->singular() }} is live. Only publishers can change it.
            @else
                You can view this {{ $type->singular() }} but not edit it in its current state ({{ strtolower($item->status->label()) }}).
            @endif
        </div>
    @endif

    <div class="row g-4">
        <div class="col-xl-8">
            <form id="content-form" method="POST" action="{{ $editing ? route("admin.{$key}.update", $item) : route("admin.{$key}.store") }}" novalidate>
                @csrf
                @if ($editing) @method('PUT') <input type="hidden" name="lock_version" value="{{ $item->lock_version }}"> @endif
                @error('lock_version')<div class="alert alert-danger pa-alert" role="alert">{{ $message }}</div>@enderror

                <fieldset @disabled($readonly)>
                    <section class="card pa-card mb-4" aria-labelledby="content-heading">
                        <div class="card-header"><h2 id="content-heading" class="h6 mb-0">Content</h2></div>
                        <div class="card-body">
                            <x-admin.field name="title" :label="$labels['title']" :value="$item->title" required data-slug-source="#field-slug" />
                            <div class="mb-3">
                                <label for="field-slug" class="form-label">URL</label>
                                <div class="input-group has-validation">
                                    <span class="input-group-text">/{{ $type->routePrefix() }}/</span>
                                    <input id="field-slug" name="slug" class="form-control @error('slug') is-invalid @enderror" value="{{ old('slug', $item->slug) }}"
                                           pattern="[a-z0-9]+(-[a-z0-9]+)*" aria-describedby="slug-help" @if (! $editing) data-slug-auto @endif>
                                    @error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div id="slug-help" class="form-text">Lowercase letters, numbers and hyphens. Leave empty to create it from the title.</div>
                            </div>
                            <x-admin.field name="excerpt" :label="$labels['excerpt']" type="textarea" :value="$item->excerpt" data-summary-editor
                                help="Shown on cards and as the search-engine description (formatting is removed there)." />
                            <x-admin.field name="body" :label="$labels['body']" type="textarea" rows="12" :value="$item->body" data-rich-editor
                                help="The main text, shown under the image. Headings, lists, quotes and links are available." />
                            <p class="small text-body-secondary mb-0"><i class="bi bi-arrow-down-circle" aria-hidden="true"></i> Optional: add galleries, buttons, cards and more in <a href="#builder-heading">Additional content</a> below.</p>
                        </div>
                    </section>

                    @foreach ($sections as $section => $fields)
                        <section class="card pa-card mb-4" aria-labelledby="section-{{ Str::slug($section) }}">
                            <div class="card-header"><h2 id="section-{{ Str::slug($section) }}" class="h6 mb-0">{{ $section }}</h2></div>
                            <div class="card-body">
                                @foreach ($fields as $name => $field)
                                    @php $value = $type->formValue($item, $name); @endphp
                                    @if ($field['type'] === 'checkbox')
                                            <div class="form-check mb-3">
                                                <input type="hidden" name="{{ $name }}" value="0">
                                                <input class="form-check-input" type="checkbox" name="{{ $name }}" value="1" id="field-{{ $name }}" @checked(old($name, $value))>
                                                <label class="form-check-label" for="field-{{ $name }}">{{ $field['label'] }}</label>
                                            </div>
                                        @elseif (in_array($field['type'], ['timezone', 'select'], true))
                                            @php $options = $field['type'] === 'timezone' ? array_combine($timezones, $timezones) : $field['options']; @endphp
                                            <div class="mb-3">
                                                <label for="field-{{ $name }}" class="form-label">{{ $field['label'] }}</label>
                                                <select id="field-{{ $name }}" name="{{ $name }}" class="form-select @error($name) is-invalid @enderror" @if (! empty($field['help'])) aria-describedby="field-{{ $name }}-help" @endif>
                                                    @foreach ($options as $optionValue => $optionLabel)
                                                        <option value="{{ $optionValue }}" @selected((string) old($name, $value) === (string) $optionValue)>{{ $optionLabel }}</option>
                                                    @endforeach
                                                </select>
                                                @if (! empty($field['help']))<div id="field-{{ $name }}-help" class="form-text">{{ $field['help'] }}</div>@endif
                                                @error($name)<div class="invalid-feedback">{{ $message }}</div>@enderror
                                            </div>
                                        @elseif ($field['type'] === 'media')
                                            <x-admin.media-picker :name="$name" :label="$field['label']" :media="$mediaFields[$name] ?? null" :kind="$field['media_kind'] ?? 'image'"
                                                :help="$field['help'] ?? null" :disabled="$readonly" />
                                        @elseif ($field['type'] === 'relation')
                                            @php
                                                $chosen = array_map('intval', (array) old($name, $relationValues[$name] ?? []));
                                                $options = $relationOptions[$name] ?? [];
                                                $targetType = app(\App\Cms\Content\ContentTypeRegistry::class)->get($field['target']);
                                            @endphp
                                            @if (empty($field['multiple']))
                                                <div class="mb-3">
                                                    <label for="field-{{ $name }}" class="form-label">{{ $field['label'] }}</label>
                                                    <select id="field-{{ $name }}" name="{{ $name }}[]" class="form-select @error($name) is-invalid @enderror" @if (! empty($field['help'])) aria-describedby="field-{{ $name }}-help" @endif>
                                                        <option value="">— None —</option>
                                                        @foreach ($options as $optionId => $optionLabel)
                                                            <option value="{{ $optionId }}" @selected(in_array((int) $optionId, $chosen, true))>{{ $optionLabel }}</option>
                                                        @endforeach
                                                    </select>
                                                    @if (! empty($field['help']))<div id="field-{{ $name }}-help" class="form-text">{{ $field['help'] }}</div>@endif
                                                </div>
                                            @else
                                                <fieldset class="mb-3">
                                                    <legend class="form-label fs-6">{{ $field['label'] }}</legend>
                                                    @if ($options === [])
                                                        <p class="small text-body-secondary mb-0">No {{ strtolower($targetType->label()) }} yet.@can($targetType->ability('create')) <a href="{{ route('admin.'.$targetType->key().'.create') }}">Add one</a>.@endcan</p>
                                                    @else
                                                        <div class="pa-checklist">
                                                            @foreach ($options as $optionId => $optionLabel)
                                                                <div class="form-check">
                                                                    <input class="form-check-input" type="checkbox" name="{{ $name }}[]" value="{{ $optionId }}" id="field-{{ $name }}-{{ $optionId }}" @checked(in_array((int) $optionId, $chosen, true))>
                                                                    <label class="form-check-label" for="field-{{ $name }}-{{ $optionId }}">{{ $optionLabel }}</label>
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    @endif
                                                </fieldset>
                                            @endif
                                        @elseif ($field['type'] === 'gallery')
                                            @php
                                                $galleryConfig = [
                                                    'rows' => old('gallery_items') === null ? $galleryItems : array_values((array) old('gallery_items')),
                                                    'endpoint' => route('admin.api.media.index'),
                                                    'uploadEndpoint' => route('admin.api.media.store'),
                                                    'canUpload' => auth()->user()->can('media.upload'),
                                                    'disabled' => $readonly,
                                                    'errors' => (object) collect($errors->getMessages())->filter(fn ($m, $k) => str_starts_with($k, 'gallery_items'))->map(fn ($m) => $m[0])->all(),
                                                ];
                                            @endphp
                                            <div data-gallery-field data-config="{{ json_encode($galleryConfig) }}">
                                                {{-- Without JavaScript: keep the current photos and videos as they are. --}}
                                                @foreach ($galleryItems as $i => $galleryRow)
                                                    @foreach (['media_id', 'video_url', 'caption', 'alt_override', 'credit'] as $key)
                                                        <input type="hidden" name="gallery_items[{{ $i }}][{{ $key }}]" value="{{ $galleryRow[$key] }}">
                                                    @endforeach
                                                @endforeach
                                                <p class="small text-body-secondary">{{ count($galleryItems) }} photos and videos.</p>
                                            </div>
                                        @elseif ($field['type'] === 'repeater')
                                            @php
                                                $rows = array_values((array) old($name, $value ?? []));
                                                $rowErrors = collect($errors->getMessages())->filter(fn ($m, $k) => $k === $name || str_starts_with($k, $name.'.'))->map(fn ($m) => $m[0])->all();
                                                $repeaterConfig = [
                                                    'name' => $name, 'label' => $field['label'], 'addLabel' => $field['add_label'] ?? 'Add', 'max' => $field['max'] ?? 30,
                                                    'fields' => collect($field['fields'])->map(fn ($sub) => ['type' => $sub['type'], 'label' => $sub['label']])->all(),
                                                    'rows' => $rows, 'errors' => (object) $rowErrors, 'disabled' => $readonly,
                                                ];
                                            @endphp
                                            <div data-repeater-field data-config="{{ json_encode($repeaterConfig) }}">
                                                {{-- Without JavaScript: the saved rows as plain inputs. --}}
                                                <p class="form-label">{{ $field['label'] }}</p>
                                                @foreach ($rows as $i => $row)
                                                    @foreach ($field['fields'] as $sub => $subField)
                                                        <label class="form-label small" for="{{ $name }}-{{ $i }}-{{ $sub }}">{{ $subField['label'] }} {{ $i + 1 }}</label>
                                                        <input class="form-control form-control-sm mb-2" id="{{ $name }}-{{ $i }}-{{ $sub }}" name="{{ $name }}[{{ $i }}][{{ $sub }}]" value="{{ $row[$sub] ?? '' }}">
                                                    @endforeach
                                                @endforeach
                                            </div>
                                        @else
                                            <x-admin.field :name="$name" :label="$field['label']" :type="$field['type'] === 'datetime' ? 'datetime-local' : $field['type']"
                                                :value="$value" :help="$field['help'] ?? null" :required="in_array('required', $field['rules'], true)" :placeholder="$field['placeholder'] ?? null" />
                                    @endif
                                @endforeach
                                @if ($section === 'When' && isset($type->fields()['timezone']))
                                    <p class="small text-body-secondary mb-0">Enter times as they are at the event's location. Visitors see them with the time zone.</p>
                                @endif
                            </div>
                        </section>
                    @endforeach

                    @if ($type->documents())
                        @php
                            $oldDocuments = old('documents');
                            if (is_array($oldDocuments)) {
                                $names = \App\Models\Media::query()->whereKey(collect($oldDocuments)->pluck('media_id')->filter()->all())->get()->keyBy('id');
                                $documents = collect($oldDocuments)->filter(fn ($row) => isset($names[(int) ($row['media_id'] ?? 0)]))
                                    ->map(fn ($row) => ['media_id' => (int) $row['media_id'], 'label' => $row['label'] ?? null, 'name' => $names[(int) $row['media_id']]->original_name, 'size' => $names[(int) $row['media_id']]->humanSize()])
                                    ->values()->all();
                            }
                            $documentsConfig = [
                                'rows' => $documents,
                                'endpoint' => route('admin.api.media.index'),
                                'uploadEndpoint' => route('admin.api.media.store'),
                                'canUpload' => auth()->user()->can('media.upload'),
                                'disabled' => $readonly,
                                'errors' => (object) collect($errors->getMessages())->filter(fn ($m, $k) => str_starts_with($k, 'documents'))->map(fn ($m) => $m[0])->all(),
                            ];
                        @endphp
                        <section class="card pa-card mb-4" aria-labelledby="documents-heading">
                            <div class="card-header"><h2 id="documents-heading" class="h6 mb-0">Documents</h2></div>
                            <div class="card-body">
                                <p class="small text-body-secondary">Reports, briefs and other files visitors can download from this {{ $type->singular() }}'s page. Only public files from the Media Library can be used.</p>
                                <div data-documents-field data-config="{{ json_encode($documentsConfig) }}">
                                    {{-- Without JavaScript: keep the current list as it is. --}}
                                    @foreach ($documents as $i => $document)
                                        <input type="hidden" name="documents[{{ $i }}][media_id]" value="{{ $document['media_id'] }}">
                                        <input type="hidden" name="documents[{{ $i }}][label]" value="{{ $document['label'] }}">
                                        <p class="mb-1"><i class="bi bi-file-earmark-text me-1" aria-hidden="true"></i>{{ $document['label'] ?: $document['name'] }}</p>
                                    @endforeach
                                </div>
                            </div>
                        </section>
                    @endif

                    <section class="card pa-card mb-4" aria-labelledby="media-heading">
                        <div class="card-header"><h2 id="media-heading" class="h6 mb-0">{{ $labels['image'] }}{{ $categories->isNotEmpty() || $type->taxonomy() ? ' & categories' : '' }}</h2></div>
                        <div class="card-body">
                            <x-admin.media-picker name="featured_media_id" :label="$labels['image']" :media="$item->featuredMedia" :disabled="$readonly" />
                            @if ($categories->isNotEmpty())
                                @php $selected = array_map('intval', old('terms', $editing ? $item->terms->where('taxonomy', $type->taxonomy())->pluck('id')->all() : [])); @endphp
                                <fieldset class="mb-3">
                                    <legend class="form-label fs-6">Categories</legend>
                                    @foreach ($categories as $category)
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="checkbox" name="terms[]" value="{{ $category->id }}" id="cat-{{ $category->id }}" @checked(in_array($category->id, $selected, true))>
                                            <label class="form-check-label" for="cat-{{ $category->id }}">{{ $category->name }}</label>
                                        </div>
                                    @endforeach
                                </fieldset>
                            @elseif ($type->taxonomy() && auth()->user()->can('taxonomies.manage'))
                                <p class="small text-body-secondary">No categories yet. <a href="{{ route('admin.terms.index', ['taxonomy' => $type->taxonomy()]) }}">Add categories</a>.</p>
                            @endif
                            <div class="form-check">
                                <input type="hidden" name="featured" value="0">
                                <input class="form-check-input" type="checkbox" name="featured" value="1" id="featured" aria-describedby="featured-help" @checked(old('featured', $item->featured))>
                                <label class="form-check-label" for="featured">Featured</label>
                                <div id="featured-help" class="form-text">Blocks can be set to list featured {{ strtolower($type->label()) }} only.</div>
                            </div>
                        </div>
                    </section>

                    <section class="card pa-card mb-4" aria-labelledby="sidebar-heading">
                        <div class="card-header"><h2 id="sidebar-heading" class="h6 mb-0">Sidebar</h2></div>
                        <div class="card-body">
                            @php $mode = old('sidebar_mode', $item->sidebar_mode ?? 'default'); @endphp
                            <fieldset class="mb-3">
                                <legend class="form-label fs-6">Show next to the content</legend>
                                @foreach (['default' => 'The default sidebar for '.strtolower($type->label()).' (set in Settings)', 'none' => 'No sidebar', 'custom' => 'A sidebar chosen for this '.$type->singular()] as $value => $label)
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="sidebar_mode" value="{{ $value }}" id="sidebar-{{ $value }}" @checked($mode === $value)>
                                        <label class="form-check-label" for="sidebar-{{ $value }}">{{ $label }}</label>
                                    </div>
                                @endforeach
                            </fieldset>
                            <div class="mb-0">
                                <label for="field-sidebar" class="form-label">Sidebar for this {{ $type->singular() }}</label>
                                <select id="field-sidebar" name="sidebar_global_block_id" class="form-select @error('sidebar_global_block_id') is-invalid @enderror" aria-describedby="sidebar-help">
                                    <option value="">— Choose —</option>
                                    <x-admin.sidebar-options :blocks="$sidebars" :selected="old('sidebar_global_block_id', $item->sidebar_global_block_id)" />
                                </select>
                                <div id="sidebar-help" class="form-text">
                                    Only used with “A sidebar chosen for this {{ $type->singular() }}”. Any published global block can be a sidebar; blocks set to “Used as: Sidebar” are listed first.
                                    Left or right is set for all {{ strtolower($type->label()) }} in @can('settings.manage')<a href="{{ route('admin.settings.general') }}#sidebars-heading">Settings → Sidebars</a>@else Settings → Sidebars @endcan.
                                    @can('global_blocks.manage')<a href="{{ route('admin.global-blocks.create') }}">Create a sidebar</a>.@endcan
                                </div>
                                @error('sidebar_global_block_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </section>

                    <section class="card pa-card mb-4" aria-labelledby="seo-heading">
                        <div class="card-header"><h2 id="seo-heading" class="h6 mb-0">Search engines &amp; social sharing</h2></div>
                        <div class="card-body">
                            <p class="small text-body-secondary">Leave empty to use the title, summary and image automatically.</p>
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
                                help="Used when this is shared on Facebook, LinkedIn, WhatsApp… (1200×630 recommended)." />
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
                        @if ($editing)
                            <span class="d-inline-flex gap-1">
                                <x-admin.status-badge :status="$item->status" />
                                @if ($item->publish_at)<span class="pa-badge pa-badge--warning">Scheduled</span>@endif
                            </span>
                        @endif
                    </div>
                    <div class="card-body">
                        @if ($editing)
                            <dl class="small mb-3 pa-meta">
                                <dt>On the website</dt>
                                <dd>
                                    @if ($item->isPublished())
                                        <a href="{{ url($item->url()) }}" target="_blank" rel="noopener">{{ $item->url() }}<span class="visually-hidden"> (opens in new tab)</span></a>
                                        · since {{ $item->published_at?->timezone($siteTimezone)->format('j M Y, H:i') }}
                                    @else
                                        Not published
                                    @endif
                                </dd>
                                @if ($item->publish_at)
                                    <dt>Scheduled</dt>
                                    <dd>{{ $item->publish_at->timezone($siteTimezone)->format('j M Y, H:i') }} ({{ $siteTimezone }})</dd>
                                @endif
                                <dt>Author</dt>
                                <dd>{{ $item->author?->name ?? '—' }}</dd>
                            </dl>
                            @if ($item->isPublished())
                                <p class="small text-body-secondary">Changes to a published {{ $type->singular() }} go live when you save.</p>
                            @endif
                        @endif

                        <div class="d-grid gap-2">
                            @if ($canEdit)
                                <button type="submit" form="content-form" class="btn btn-primary">{{ ! $editing ? 'Create draft' : ($item->isPublished() ? 'Save and update live' : 'Save') }}</button>
                            @endif
                            @if ($editing)
                                <a href="{{ route("admin.{$key}.preview", $item) }}" class="btn btn-outline-secondary" target="_blank" rel="noopener">
                                    <i class="bi bi-eye" aria-hidden="true"></i> Preview<span class="visually-hidden"> (opens in new tab)</span>
                                </a>
                            @endif
                        </div>

                        @if ($editing && $actions !== [])
                            <hr>
                            <p class="small fw-semibold mb-2">Workflow</p>
                            <div class="d-grid gap-2">
                                @foreach ($actions as $action)
                                    @continue(in_array($action, [WorkflowAction::Schedule, WorkflowAction::RequestChanges], true))
                                    <form method="POST" action="{{ route("admin.{$key}.workflow", $item) }}"
                                          @if (in_array($action, [WorkflowAction::Unpublish, WorkflowAction::Archive], true)) data-confirm="{{ $action->label() }} “{{ $item->title }}”? It will no longer be visible on the website." @endif>
                                        @csrf
                                        <input type="hidden" name="action" value="{{ $action->value }}">
                                        <button type="submit" @class([
                                            'btn w-100',
                                            'btn-success' => $action === WorkflowAction::Publish,
                                            'btn-outline-danger' => in_array($action, [WorkflowAction::Unpublish, WorkflowAction::Archive], true),
                                            'btn-outline-primary' => ! in_array($action, [WorkflowAction::Publish, WorkflowAction::Unpublish, WorkflowAction::Archive], true),
                                        ])>{{ $action->label() }}</button>
                                    </form>
                                @endforeach

                                @if (in_array(WorkflowAction::RequestChanges, $actions, true))
                                    <form method="POST" action="{{ route("admin.{$key}.workflow", $item) }}" class="border rounded p-2">
                                        @csrf
                                        <input type="hidden" name="action" value="request_changes">
                                        <label for="rc-note" class="form-label small mb-1">Note for the author</label>
                                        <textarea id="rc-note" name="note" rows="2" class="form-control form-control-sm mb-2" maxlength="1000"></textarea>
                                        <button type="submit" class="btn btn-sm btn-outline-secondary w-100">Request changes</button>
                                    </form>
                                @endif

                                @if (in_array(WorkflowAction::Schedule, $actions, true))
                                    <form method="POST" action="{{ route("admin.{$key}.workflow", $item) }}" class="border rounded p-2">
                                        @csrf
                                        <input type="hidden" name="action" value="schedule">
                                        <label for="schedule-at" class="form-label small mb-1">Publish automatically at ({{ $siteTimezone }})</label>
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
                            <a href="{{ route("admin.{$key}.revisions", $item) }}" class="small">All &amp; compare</a>
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

                    @can('delete', $item)
                        <form method="POST" action="{{ route("admin.{$key}.destroy", $item) }}" data-confirm="Delete “{{ $item->title }}”? @if ($item->isPublished()) It will be removed from the website. @endif">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-link text-danger p-0"><i class="bi bi-trash" aria-hidden="true"></i> Delete {{ $type->singular() }}</button>
                        </form>
                    @endcan
                @endif
            </aside>
        </div>
    </div>

    <x-admin.block-builder :blocks="$blocks" form="content-form" :readonly="$readonly" context="page" heading="Additional content (optional)" />
    {{-- React refresh is already added by the block builder component. --}}
    @push('islands')
        @vite('resources/js/admin/islands/summary-editor.jsx')
        @vite('resources/js/admin/islands/content-fields.jsx')
    @endpush
</x-admin.layout>
