<?php

namespace App\Http\Controllers\Admin;

use App\Cms\Blocks\BlockTreeRepository;
use App\Http\Controllers\Controller;
use App\Models\GlobalBlock;
use App\Services\Blocks\GlobalBlockService;
use App\Services\Content\ContentReferenceService;
use App\Services\Revisions\RevisionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Global blocks admin (CMS-ARCHITECTURE.md §12). Route middleware: global_blocks.manage.
 */
class GlobalBlockController extends Controller
{
    public function __construct(private readonly GlobalBlockService $globals) {}

    public function index(Request $request, ContentReferenceService $references): View
    {
        $q = (string) ($request->validate(['q' => ['nullable', 'string', 'max:100']])['q'] ?? '');

        $items = GlobalBlock::query()
            ->when($q !== '', fn ($query) => $query->where('name', 'like', "%{$q}%"))
            ->orderBy('name')
            ->paginate(30)
            ->withQueryString();

        return view('admin.global-blocks.index', [
            'items' => $items,
            'usage' => $items->getCollection()->mapWithKeys(fn (GlobalBlock $global) => [$global->id => $references->usagesOf($global)->count()]),
            'q' => $q,
        ]);
    }

    public function create(): View
    {
        return view('admin.global-blocks.form', ['item' => new GlobalBlock, 'blocks' => [], 'usages' => collect(), 'autosave' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $global = $this->globals->create($request->user(), $this->validated($request));

        return redirect()->route('admin.global-blocks.edit', $global)->with('success', __('Global block created. Publish it to make it available on pages.'));
    }

    public function edit(Request $request, GlobalBlock $globalBlock, BlockTreeRepository $blocks, ContentReferenceService $references, RevisionService $revisions): View
    {
        return view('admin.global-blocks.form', [
            'item' => $globalBlock,
            'blocks' => $blocks->load($globalBlock),
            'usages' => $references->usagesOf($globalBlock),
            'roles' => $this->globals->roles($globalBlock),
            'autosave' => $revisions->pendingAutosave($globalBlock, $request->user()),
        ]);
    }

    public function update(Request $request, GlobalBlock $globalBlock): RedirectResponse
    {
        $this->globals->update($request->user(), $globalBlock, $this->validated($request), (int) $request->input('lock_version'));

        return redirect()->route('admin.global-blocks.edit', $globalBlock)->with('success', __('Saved. Publish the changes to update every page that uses this block.'));
    }

    public function publish(Request $request, GlobalBlock $globalBlock): RedirectResponse
    {
        $this->globals->publish($request->user(), $globalBlock);

        return redirect()->route('admin.global-blocks.edit', $globalBlock)->with('success', __('Published. Every page that uses this block now shows the new version.'));
    }

    public function destroy(Request $request, GlobalBlock $globalBlock): RedirectResponse
    {
        $this->globals->delete($request->user(), $globalBlock);

        return redirect()->route('admin.global-blocks.index')->with('success', __('Global block deleted.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:191', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'description' => ['nullable', 'string', 'max:1000'],
            'kind' => ['nullable', Rule::in(array_keys(GlobalBlock::KINDS))],
            'blocks' => ['nullable', 'string', 'max:'.((int) config('pacms.blocks.max_payload_kb') * 1024), 'json'],
            'lock_version' => ['nullable', 'integer'],
        ]);

        if ($request->filled('blocks')) {
            $data['blocks'] = json_decode((string) $request->input('blocks'), true, 64) ?? [];
        } else {
            unset($data['blocks']);
        }
        unset($data['lock_version']);

        return $data;
    }
}
