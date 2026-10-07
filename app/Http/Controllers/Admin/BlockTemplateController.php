<?php

namespace App\Http\Controllers\Admin;

use App\Cms\Blocks\BlockTreeRepository;
use App\Http\Controllers\Controller;
use App\Models\BlockTemplate;
use App\Services\Blocks\BlockTemplateService;
use App\Services\Revisions\RevisionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Block templates admin (CMS-ARCHITECTURE.md §12). Route middleware: templates.manage.
 */
class BlockTemplateController extends Controller
{
    public function __construct(private readonly BlockTemplateService $templates) {}

    public function index(Request $request): View
    {
        $q = (string) ($request->validate(['q' => ['nullable', 'string', 'max:100']])['q'] ?? '');

        return view('admin.block-templates.index', [
            'items' => BlockTemplate::query()
                ->with('creator:id,name')
                ->when($q !== '', fn ($query) => $query->where('name', 'like', "%{$q}%"))
                ->orderBy('category')->orderBy('name')
                ->paginate(30)
                ->withQueryString(),
            'q' => $q,
        ]);
    }

    public function create(): View
    {
        return view('admin.block-templates.form', ['item' => new BlockTemplate, 'blocks' => [], 'autosave' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $template = $this->templates->create($request->user(), $this->validated($request));

        return redirect()->route('admin.block-templates.edit', $template)->with('success', __('Template saved.'));
    }

    public function edit(Request $request, BlockTemplate $blockTemplate, BlockTreeRepository $blocks, RevisionService $revisions): View
    {
        return view('admin.block-templates.form', [
            'item' => $blockTemplate,
            'blocks' => $blocks->load($blockTemplate),
            'autosave' => $revisions->pendingAutosave($blockTemplate, $request->user()),
        ]);
    }

    public function update(Request $request, BlockTemplate $blockTemplate): RedirectResponse
    {
        $this->templates->update($request->user(), $blockTemplate, $this->validated($request), (int) $request->input('lock_version'));

        return redirect()->route('admin.block-templates.edit', $blockTemplate)->with('success', __('Template saved. Pages that already used it are not changed.'));
    }

    public function destroy(Request $request, BlockTemplate $blockTemplate): RedirectResponse
    {
        $this->templates->delete($request->user(), $blockTemplate);

        return redirect()->route('admin.block-templates.index')->with('success', __('Template deleted. Pages that used it keep their copy.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'scope' => ['required', Rule::in(array_keys(BlockTemplate::SCOPES))],
            'category' => ['nullable', 'string', 'max:64'],
            'status' => ['required', Rule::in(['published', 'draft'])],
            'blocks' => ['required', 'string', 'max:'.((int) config('pacms.blocks.max_payload_kb') * 1024), 'json'],
        ]);
        $data['blocks'] = json_decode((string) $data['blocks'], true, 64) ?? [];

        return $data;
    }
}
