<?php

namespace App\Http\Controllers\Admin;

use App\Cms\Blocks\BlockTreeRepository;
use App\Http\Controllers\Controller;
use App\Models\BlockType;
use App\Services\Blocks\CustomBlockTypeService;
use App\Services\Content\ContentReferenceService;
use App\Services\Revisions\RevisionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Custom block types admin (CMS-ARCHITECTURE.md §11): field builder + structure builder.
 * Route middleware: block_types.manage.
 */
class CustomBlockTypeController extends Controller
{
    public function __construct(private readonly CustomBlockTypeService $types) {}

    public function index(ContentReferenceService $references): View
    {
        $items = BlockType::query()->custom()->orderBy('name')->get();

        return view('admin.block-types.index', [
            'items' => $items,
            'usage' => $items->mapWithKeys(fn (BlockType $type) => [$type->id => $references->usagesOf($type)->count()]),
        ]);
    }

    public function create(): View
    {
        return view('admin.block-types.create', ['item' => new BlockType(['icon' => 'bi-puzzle'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'description' => ['nullable', 'string', 'max:500'],
            'icon' => ['nullable', 'string', 'regex:/^bi-[a-z0-9-]{1,60}$/'],
        ]);
        $type = $this->types->create($request->user(), $data);

        return redirect()->route('admin.block-types.edit', $type)->with('success', __('Block type created. Add its fields and build its layout, then publish it.'));
    }

    public function edit(Request $request, BlockType $blockType, BlockTreeRepository $blocks, ContentReferenceService $references, RevisionService $revisions): View
    {
        abort_unless($blockType->isCustom(), 404);

        return view('admin.block-types.edit', [
            'item' => $blockType,
            'blocks' => $blocks->load($blockType),
            'usage' => $references->usagesOf($blockType)->count(),
            'autosave' => $revisions->pendingAutosave($blockType, $request->user()),
        ]);
    }

    public function update(Request $request, BlockType $blockType): RedirectResponse
    {
        abort_unless($blockType->isCustom(), 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'icon' => ['nullable', 'string', 'regex:/^bi-[a-z0-9-]{1,60}$/'],
            'fields_json' => ['required', 'string', 'max:200000', 'json'],
            'blocks' => ['required', 'string', 'max:'.((int) config('pacms.blocks.max_payload_kb') * 1024), 'json'],
            'lock_version' => ['required', 'integer'],
        ]);

        $this->types->update($request->user(), $blockType, [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'icon' => $data['icon'] ?? null,
            'fields' => json_decode($data['fields_json'], true, 32) ?? [],
            'blocks' => json_decode($data['blocks'], true, 64) ?? [],
        ], (int) $data['lock_version']);

        return redirect()->route('admin.block-types.edit', $blockType)->with('success', __('Saved. Publish to update every block of this type.'));
    }

    public function publish(Request $request, BlockType $blockType): RedirectResponse
    {
        $type = $this->types->publish($request->user(), $blockType);

        return redirect()->route('admin.block-types.edit', $blockType)->with('success', __('Version :version published.', ['version' => $type->version]));
    }

    public function toggle(Request $request, BlockType $blockType): RedirectResponse
    {
        $type = $this->types->setEnabled($request->user(), $blockType, $blockType->status === 'disabled');

        return back()->with('success', $type->status === 'disabled'
            ? __('Disabled: editors can no longer add this block. Existing blocks keep showing.')
            : __('Enabled: editors can add this block again.'));
    }

    public function destroy(Request $request, BlockType $blockType): RedirectResponse
    {
        $this->types->delete($request->user(), $blockType);

        return redirect()->route('admin.block-types.index')->with('success', __('Block type deleted.'));
    }
}
