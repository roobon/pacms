@props([
    'blocks' => [],
    'form',
    'readonly' => false,
    'context' => 'page',
    'owner' => null,        {{-- ['type' => 'page', 'id' => 1] for autosave; null while creating --}}
    'autosave' => null,     {{-- pending autosave revision of the current user --}}
    'fields' => null,       {{-- custom block type: working field definitions (structure context) --}}
    'heading' => 'Page content',
])
{{-- Block Builder (React island). Its hidden inputs belong to the owner's form, so one
     "Save" stores the fields and the block tree together. --}}
@php
    $blockErrors = collect($errors->getMessages())->filter(fn ($m, $key) => str_starts_with($key, 'blocks'))->all();
    $fieldErrors = collect($errors->getMessages())->filter(fn ($m, $key) => str_starts_with($key, 'fields'))->all();
    $oldBlocks = old('blocks');
    $oldFields = old('fields_json');
    $builderData = [
        'blocks' => $oldBlocks !== null ? (json_decode($oldBlocks, true) ?? []) : $blocks,
        'errors' => $blockErrors + $fieldErrors,
        'readonly' => $readonly,
        'context' => $context,
        'owner' => $owner,
        'fields' => $fields === null ? null : ($oldFields !== null ? (json_decode($oldFields, true) ?? []) : $fields),
        'autosave' => $autosave ? ['saved_at' => $autosave->created_at?->toIso8601String(), 'blocks' => $autosave->snapshot['blocks'] ?? []] : null,
        'endpoints' => [
            'definitions' => route('admin.api.blocks.definitions'),
            'resolve' => route('admin.api.blocks.resolve'),
            'linkTargets' => route('admin.api.link-targets'),
            'media' => route('admin.api.media.index'),
            'mediaUpload' => route('admin.api.media.store'),
            'previewFrame' => route('preview.builder', [], false),
            'globals' => route('admin.api.globals.index'),
            'storeGlobal' => route('admin.api.globals.store'),
            'detachGlobal' => route('admin.api.globals.detach', ['global' => '__ID__']),
            'templates' => route('admin.api.templates.index'),
            'template' => route('admin.api.templates.show', ['template' => '__ID__']),
            'storeTemplate' => route('admin.api.templates.store'),
            'autosave' => route('admin.api.autosave'),
        ],
    ];
@endphp
<section {{ $attributes->class(['card pa-card mt-4']) }} aria-labelledby="builder-heading">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h2 id="builder-heading" class="h6 mb-0">{{ $heading }}</h2>
        @if ($blockErrors || $fieldErrors)
            @php($count = count($blockErrors) + count($fieldErrors))
            <span class="pa-badge pa-badge--danger">{{ $count }} {{ \Illuminate\Support\Str::plural('error', $count) }}</span>
        @endif
    </div>
    <div class="card-body p-0">
        <input type="hidden" id="blocks-input" name="blocks" form="{{ $form }}" value="{{ $oldBlocks ?? json_encode($blocks) }}">
        @if ($fields !== null)
            <input type="hidden" id="fields-input" name="fields_json" form="{{ $form }}" value="{{ $oldFields ?? json_encode($fields) }}">
        @endif
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
