<?php

namespace App\Http\Controllers\Admin;

use App\Cms\Blocks\BlockTreeRepository;
use App\Http\Controllers\Controller;
use App\Models\BlockTemplate;
use App\Services\Blocks\BlockTemplateService;
use App\Services\Revisions\RevisionService;
use App\Services\Starter\StarterKitService;
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
        // A serial number ("P-07", "p7", "S-12") finds that shipped template.
        $numbers = app(StarterKitService::class)->manifest()['numbers'];
        $numbered = preg_match('/^\s*([ps])-?0*(\d{1,3})\s*$/i', $q, $m)
            ? array_keys(array_filter($numbers, fn (string $n) => $n === sprintf('%s-%02d', strtoupper($m[1]), (int) $m[2])))
            : null;
        // Shipped templates in SN order (P-01…, then S-01…), then the site's own by name.
        asort($numbers);
        $order = array_keys($numbers);
        $marks = implode(',', array_fill(0, count($order), '?'));

        $items = BlockTemplate::query()
            ->with('creator:id,name')
            ->when($numbered !== null, fn ($query) => $query->whereIn('slug', $numbered))
            ->when($numbered === null && $q !== '', fn ($query) => $query->where('name', 'like', "%{$q}%"))
            ->when($order !== [], fn ($query) => $query
                ->orderByRaw("FIELD(slug, {$marks}) = 0", $order)
                ->orderByRaw("FIELD(slug, {$marks})", $order))
            ->orderBy('name')
            ->paginate(30)
            ->withQueryString();

        return view('admin.block-templates.index', [
            'items' => $items,
            'widths' => StarterKitService::widths($items->getCollection()),
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
