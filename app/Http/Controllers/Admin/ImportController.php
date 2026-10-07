<?php

namespace App\Http\Controllers\Admin;

use App\Cms\Exchange\SchemaGenerator;
use App\Http\Controllers\Controller;
use App\Jobs\RunImport;
use App\Models\ImportJob;
use App\Models\Media;
use App\Models\Page;
use App\Services\Exchange\ImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * JSON import (CMS-BLOCK-SCHEMA.md §17): paste or upload → report and preview → confirm
 * (asset choices, target) → draft. Route middleware: import.run.
 */
class ImportController extends Controller
{
    public function __construct(private readonly ImportService $imports) {}

    public function index(Request $request, SchemaGenerator $schema): View
    {
        return view('admin.import.index', [
            'jobs' => ImportJob::query()->where('user_id', $request->user()->id)->latest('id')->limit(15)->get(),
            'prompt' => $schema->promptHelper(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'json' => ['nullable', 'required_without:file', 'string', 'max:2200000'],
            'file' => ['nullable', 'required_without:json', 'file', 'max:2048', 'mimetypes:application/json,text/plain'],
        ], ['json.required_without' => __('Paste JSON or choose a .json file.')]);

        $json = $request->hasFile('file') ? (string) file_get_contents($request->file('file')->getRealPath()) : (string) $request->input('json');
        $job = $this->imports->analyse($json, $request->user(), $request->file('file')?->getClientOriginalName());

        return redirect()->route('admin.import.show', $job);
    }

    public function show(Request $request, ImportJob $import): View
    {
        $this->authorizeJob($request, $import);
        $document = $import->documentData();

        return view('admin.import.show', [
            'job' => $import,
            'report' => $import->report,
            'blocks' => $this->withoutPendingAssets((array) ($document['blocks'] ?? [])),
            'pages' => in_array($import->kind, ['block', 'section'], true)
                ? Page::query()->orderBy('path')->get(['id', 'title', 'path'])->filter(fn (Page $page) => Gate::allows('update', $page))->values()
                : collect(),
            'mediaChoices' => Media::query()->where('kind', 'image')->latest('id')->limit(200)->get(['id', 'original_name', 'alt']),
            'canCreatePages' => $request->user()->can('pages.create'),
            'canTemplates' => $request->user()->can('templates.manage'),
        ]);
    }

    public function confirm(Request $request, ImportJob $import): RedirectResponse
    {
        $this->authorizeJob($request, $import);
        abort_unless($import->status === ImportJob::AWAITING, 409);

        $assetKeys = array_column((array) $import->assets, 'key');
        $data = $request->validate([
            'target' => ['required', Rule::in(['new', 'page', 'template'])],
            'page_id' => ['nullable', 'required_if:target,page', 'integer', Rule::exists('pages', 'id')->whereNull('deleted_at')],
            'assets' => ['array'],
            'assets.*.strategy' => ['nullable', Rule::in(['download', 'existing', 'skip'])],
            'assets.*.media_id' => ['nullable', 'integer', Rule::exists('media', 'id')],
            'acknowledge' => [($import->report['counts']['warning'] ?? 0) > 0 ? 'accepted' : 'nullable'],
        ], ['acknowledge.accepted' => __('Confirm that you have read the warnings.')]);

        // Permission for what will be created (§17): pages, templates, or editing the chosen page.
        match (true) {
            $import->kind === 'page' => abort_unless($request->user()->can('pages.create'), 403),
            $import->kind === 'template' || $data['target'] === 'template' => abort_unless($request->user()->can('templates.manage'), 403),
            default => Gate::authorize('update', Page::query()->findOrFail((int) ($data['page_id'] ?? 0))),
        };

        $options = [
            'target' => $import->kind === 'page' ? 'new' : ($import->kind === 'template' ? 'template' : $data['target']),
            'page_id' => isset($data['page_id']) ? (int) $data['page_id'] : null,
            'assets' => array_intersect_key((array) ($data['assets'] ?? []), array_flip($assetKeys)),
        ];

        if ($this->imports->claim($import, $options)) {
            RunImport::dispatch($import->id, $request->user()->id);
        }

        return redirect()->route('admin.import.show', $import);
    }

    public function report(Request $request, ImportJob $import): JsonResponse
    {
        $this->authorizeJob($request, $import);

        return response()->json([
            'import' => $import->only(['id', 'kind', 'status', 'title', 'schema_version', 'result_type', 'result_id', 'created_at', 'updated_at']),
            'assets' => $import->assets,
            'report' => $import->report,
        ], 200, ['Content-Disposition' => 'attachment; filename="import-'.$import->id.'-report.json"'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function schema(SchemaGenerator $schema): JsonResponse
    {
        return response()->json($schema->document(), 200, ['Content-Disposition' => 'attachment; filename="pacms-1.0.schema.json"'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function authorizeJob(Request $request, ImportJob $import): void
    {
        abort_unless($import->user_id === $request->user()->id || $request->user()->can('activity_log.view'), 403);
    }

    /**
     * For the preview: images that are not downloaded yet are left out.
     *
     * @param  array<mixed>  $value
     * @return array<mixed>
     */
    private function withoutPendingAssets(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                if (array_keys($item) === ['$asset']) {
                    unset($value[$key]);
                } else {
                    $value[$key] = $this->withoutPendingAssets($item);
                }
            }
        }

        return $value;
    }
}
