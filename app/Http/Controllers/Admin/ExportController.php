<?php

namespace App\Http\Controllers\Admin;

use App\Cms\Blocks\BlockTreeValidator;
use App\Cms\Exchange\Exporter;
use App\Http\Controllers\Controller;
use App\Models\BlockTemplate;
use App\Models\Page;
use App\Services\ActivityLog\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * JSON export (CMS-BLOCK-SCHEMA.md §18): a page, a template, or blocks selected in the
 * builder. Route middleware: export.run.
 */
class ExportController extends Controller
{
    public function __construct(private readonly Exporter $exporter, private readonly ActivityLogger $logger) {}

    public function page(Request $request, Page $page): JsonResponse
    {
        Gate::authorize('view', $page);
        $this->logger->log('export.page', $page, [], $request->user());

        return $this->download($this->exporter->page($page), 'page-'.$page->slug);
    }

    public function template(Request $request, BlockTemplate $blockTemplate): JsonResponse
    {
        abort_unless($request->user()->can('templates.manage'), 403);
        $this->logger->log('export.template', $blockTemplate, [], $request->user());

        return $this->download($this->exporter->template($blockTemplate), 'template-'.$blockTemplate->slug);
    }

    /**
     * Blocks from the builder ("Export as JSON" on a block).
     */
    public function blocks(Request $request, BlockTreeValidator $validator): JsonResponse
    {
        abort_unless($request->user()->can('pages.view'), 403);

        $data = $request->validate([
            'blocks' => ['required', 'array', 'min:1'],
            'title' => ['nullable', 'string', 'max:255'],
            'context' => ['nullable', 'in:page,global,template'],
        ]);
        $nodes = $validator->validate($data['blocks'], $request->user(), $data['context'] ?? BlockTreeValidator::CONTEXT_PAGE);

        return $this->download($this->exporter->blocks($nodes, $data['title'] ?? null), 'blocks-'.Str::slug($data['title'] ?? 'export'));
    }

    /**
     * @param  array<string, mixed>  $document
     */
    private function download(array $document, string $name): JsonResponse
    {
        return response()->json($document, 200, [
            'Content-Disposition' => 'attachment; filename="'.Str::limit(Str::slug($name), 80, '').'.pacms.json"',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
