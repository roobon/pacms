@php
    $summary = $report['summary'] ?? [];
    $counts = $report['counts'] ?? ['error' => 0, 'warning' => 0, 'info' => 0];
    $entries = collect($report['entries'] ?? []);
    $awaiting = $job->status === 'awaiting_confirmation' && ! $job->hasErrors();
    $sections = \App\Cms\Exchange\ImportReport::SECTIONS;
@endphp
@php($heading = $job->title ?: ($job->kind === 'unknown' ? 'Import check' : ucfirst($job->kind).' import'))
<x-admin.layout :title="$heading">
    <x-admin.page-header :title="$heading" :subtitle="($job->kind === 'unknown' ? 'Document not readable' : ucfirst($job->kind)).' · schema '.$job->schema_version.' · '.$job->created_at?->toDayDateTimeString()">
        <x-slot:actions>
            <a href="{{ route('admin.import.report', $job) }}" class="btn btn-outline-secondary"><i class="bi bi-download" aria-hidden="true"></i> Report (JSON)</a>
            <a href="{{ route('admin.import.index') }}" class="btn btn-link">New import</a>
        </x-slot:actions>
    </x-admin.page-header>

    @if ($job->status === 'importing')
        <meta http-equiv="refresh" content="3">
        <div class="alert alert-info pa-alert" role="status"><span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Importing… images are being downloaded. This page refreshes by itself.</div>
    @elseif ($job->status === 'completed')
        @php($created = $summary['created'] ?? [])
        <div class="alert alert-success pa-alert" role="status">
            <i class="bi bi-check-circle" aria-hidden="true"></i>
            Imported as a draft: <strong>{{ $created['title'] ?? '' }}</strong> ({{ $created['status'] ?? 'draft' }}).
            @if ($job->result_type === 'page')
                <a href="{{ route('admin.pages.edit', $job->result_id) }}" class="alert-link">Open the page</a> to review and publish it.
            @elseif ($job->result_type === 'block_template')
                <a href="{{ route('admin.block-templates.edit', $job->result_id) }}" class="alert-link">Open the template</a>.
            @endif
        </div>
    @elseif ($job->hasErrors())
        <div class="alert alert-danger pa-alert" role="alert">
            <i class="bi bi-x-octagon" aria-hidden="true"></i>
            This document cannot be imported yet: {{ trans_choice('fix the error below|fix the :count errors below', $counts['error'], ['count' => $counts['error']]) }} and check it again.
        </div>
    @endif

    <div class="row g-4">
        <div class="col-xl-8">
            <section class="card pa-card mb-4" aria-labelledby="report-heading">
                <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
                    <h2 id="report-heading" class="h6 mb-0">Report</h2>
                    <span>
                        <span class="pa-badge pa-badge--danger">{{ trans_choice(':count error|:count errors', $counts['error'], ['count' => $counts['error']]) }}</span>
                        <span class="pa-badge pa-badge--warning">{{ trans_choice(':count warning|:count warnings', $counts['warning'], ['count' => $counts['warning']]) }}</span>
                        <span class="pa-badge pa-badge--neutral">{{ trans_choice(':count note|:count notes', $counts['info'], ['count' => $counts['info']]) }}</span>
                    </span>
                </div>
                @if ($entries->isEmpty())
                    <div class="card-body"><i class="bi bi-check-circle text-success" aria-hidden="true"></i> No problems found.</div>
                @else
                    @foreach ($sections as $section => $label)
                        @php($group = $entries->where('section', $section))
                        @if ($group->isNotEmpty())
                            <h3 class="h6 px-3 pt-3 mb-2">{{ $label }}</h3>
                            <ul class="list-group list-group-flush mb-2">
                                @foreach ($group as $entry)
                                    <li class="list-group-item small d-flex gap-2">
                                        <span class="pa-badge pa-badge--{{ ['error' => 'danger', 'warning' => 'warning', 'info' => 'neutral'][$entry['level']] }} flex-shrink-0">{{ $entry['level'] }}</span>
                                        <span>
                                            {{ $entry['message'] }}
                                            @if ($entry['path'] || $entry['key'])
                                                <br><code>{{ $entry['path'] }}</code>@if ($entry['key']) <span class="text-body-secondary">(key “{{ $entry['key'] }}”)</span>@endif
                                            @endif
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    @endforeach
                @endif
            </section>
        </div>

        <div class="col-xl-4">
            <section class="card pa-card mb-4" aria-labelledby="summary-heading">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h2 id="summary-heading" class="h6 mb-0">Summary</h2>
                    <x-admin.import-status :job="$job" />
                </div>
                <dl class="card-body small mb-0 pa-dl">
                    <dt>Kind</dt><dd>{{ $job->kind === 'unknown' ? '—' : $job->kind }}</dd>
                    @if (! empty($summary['generator']))<dt>Generated by</dt><dd>{{ $summary['generator'] }}</dd>@endif
                    <dt>Blocks</dt><dd>{{ $summary['blocks'] ?? 0 }}@if (! empty($summary['dynamic_blocks'])) ({{ $summary['dynamic_blocks'] }} with live content)@endif</dd>
                    @if (! empty($summary['block_types']))
                        <dt>Types</dt><dd>{{ collect($summary['block_types'])->map(fn ($n, $t) => "{$t} ×{$n}")->implode(', ') }}</dd>
                    @endif
                    <dt>Images</dt><dd>{{ $summary['assets'] ?? 0 }}</dd>
                    @if (! empty($summary['notes']))<dt>Notes</dt><dd class="text-pre-wrap">{{ $summary['notes'] }}</dd>@endif
                </dl>
            </section>

            @if ($awaiting)
                <form id="import-confirm" method="POST" action="{{ route('admin.import.confirm', $job) }}" class="card pa-card" novalidate>
                    @csrf
                    <div class="card-header"><h2 class="h6 mb-0">2. Import</h2></div>
                    <div class="card-body">
                        @if ($counts['warning'] > 0)
                            {{-- Warnings must be acknowledged (CMS-BLOCK-SCHEMA.md §16): shown first so it is not missed. --}}
                            <div class="pa-ack mb-3 @error('acknowledge') is-invalid @enderror">
                                <div class="form-check mb-0">
                                    <input class="form-check-input" type="checkbox" name="acknowledge" value="1" id="acknowledge" aria-describedby="acknowledge-help" @checked(old('acknowledge'))>
                                    <label class="form-check-label small fw-semibold" for="acknowledge">I have read the {{ trans_choice(':count warning|:count warnings', $counts['warning'], ['count' => $counts['warning']]) }} in the report</label>
                                </div>
                                <div id="acknowledge-help" class="form-text mb-0">Warnings name what was changed or left out. The rest imports normally.</div>
                                @error('acknowledge')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                            </div>
                        @endif

                        @if ($job->kind === 'page')
                            <input type="hidden" name="target" value="new">
                            <p class="small">Creates a new <strong>draft page</strong>. If the URL is taken, a number is added.</p>
                        @elseif ($job->kind === 'template')
                            <input type="hidden" name="target" value="template">
                            <p class="small">Creates a new <strong>template</strong>, hidden from the builder until you offer it.</p>
                        @else
                            <fieldset class="mb-3">
                                <legend class="form-label fs-6 fw-semibold">Where should the blocks go?</legend>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="target" value="page" id="target-page" @checked(old('target', 'page') === 'page') @disabled($pages->isEmpty())>
                                    <label class="form-check-label" for="target-page">At the end of a page (as unpublished changes)</label>
                                </div>
                                <select name="page_id" class="form-select form-select-sm mt-1 mb-2 @error('page_id') is-invalid @enderror" aria-label="Page">
                                    @foreach ($pages as $page)
                                        <option value="{{ $page->id }}" @selected((int) old('page_id') === $page->id)>{{ $page->title }} (/{{ $page->path }})</option>
                                    @endforeach
                                </select>
                                @error('page_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                @if ($canTemplates)
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="target" value="template" id="target-template" @checked(old('target') === 'template' || $pages->isEmpty())>
                                        <label class="form-check-label" for="target-template">As a new template</label>
                                    </div>
                                @endif
                            </fieldset>
                        @endif

                        @if (! empty($job->assets))
                            <fieldset class="mb-3">
                                <legend class="form-label fs-6 fw-semibold">Images</legend>
                                @foreach ($job->assets as $asset)
                                    @php($key = $asset['key'])
                                    <div class="pa-asset-choice">
                                        <div class="small fw-semibold">{{ $key }}</div>
                                        <div class="small text-body-secondary text-break">{{ $asset['url'] ?? ($asset['media'] ? 'Media #'.$asset['media'] : '—') }}</div>
                                        @if ($asset['alt'])<div class="small">Alt: {{ $asset['alt'] }}</div>@endif
                                        <div class="d-flex gap-2 mt-1">
                                            <select name="assets[{{ $key }}][strategy]" class="form-select form-select-sm" aria-label="What to do with {{ $key }}">
                                                <option value="download" @selected($asset['strategy'] === 'download') @disabled(empty($asset['url']))>Download into the library</option>
                                                <option value="existing" @selected($asset['strategy'] === 'existing')>Use a library image</option>
                                                <option value="skip" @selected($asset['strategy'] === 'skip')>Leave empty</option>
                                            </select>
                                            <select name="assets[{{ $key }}][media_id]" class="form-select form-select-sm" aria-label="Library image for {{ $key }}">
                                                <option value="">—</option>
                                                @foreach ($mediaChoices as $media)
                                                    <option value="{{ $media->id }}" @selected((int) ($asset['media'] ?? 0) === $media->id)>#{{ $media->id }} {{ \Illuminate\Support\Str::limit($media->alt ?: $media->original_name, 40) }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                @endforeach
                                <p class="form-text mb-0">Downloads are checked like uploads (type, size, content). A failed download leaves the image empty — nothing is substituted.</p>
                            </fieldset>
                        @endif

                        <button type="submit" class="btn btn-primary w-100">Import as draft</button>
                    </div>
                </form>
            @endif
        </div>
    </div>

    @if ($job->hasErrors() && $job->source)
        <form method="POST" action="{{ route('admin.import.store') }}" class="card pa-card mb-4" novalidate>
            @csrf
            <div class="card-header d-flex justify-content-between align-items-center">
                <h2 class="h6 mb-0">Your document</h2>
                <button type="submit" class="btn btn-sm btn-primary">Check again</button>
            </div>
            <div class="card-body">
                <label for="retry-json" class="form-label small">Correct the JSON here (or ask the AI assistant to fix the errors above), then check it again.</label>
                <textarea id="retry-json" name="json" rows="16" class="form-control font-monospace small">{{ $job->source }}</textarea>
            </div>
        </form>
    @endif

    @if ($blocks !== [])
        <x-admin.block-builder :blocks="$blocks" form="import-confirm" :readonly="true" :context="$job->kind === 'template' ? 'template' : 'page'" :pending-assets="$pendingAssets"
            :heading="$pendingAssets === [] ? 'Preview' : 'Preview (images to be downloaded appear after the import)'" />
    @endif
</x-admin.layout>
