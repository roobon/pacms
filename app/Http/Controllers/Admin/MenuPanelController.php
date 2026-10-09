<?php

namespace App\Http\Controllers\Admin;

use App\Cms\Blocks\BlockTreeRepository;
use App\Http\Controllers\Controller;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Services\Navigation\MenuService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Mega panels (Phase 9B): the block tree a top-level menu item opens, edited with the block
 * builder. Saved live (menus are not staged).
 */
class MenuPanelController extends Controller
{
    public function __construct(private readonly MenuService $menus) {}

    public function edit(Menu $menu, MenuItem $item, BlockTreeRepository $tree): View
    {
        $this->check($menu, $item);

        return view('admin.menus.panel', [
            'menu' => $menu,
            'item' => $item,
            'title' => $item->label ?: $this->menus->labelOf($item),
            'blocks' => $item->is_mega ? $tree->load($item) : [],
        ]);
    }

    public function update(Request $request, Menu $menu, MenuItem $item): RedirectResponse
    {
        $this->check($menu, $item);
        $data = $request->validate(['blocks' => ['present', 'nullable', 'string', 'max:'.((int) config('pacms.blocks.max_payload_kb') * 1024), 'json']]);
        $this->menus->savePanel($request->user(), $item, json_decode((string) ($data['blocks'] ?? '[]'), true, 64) ?? []);

        return back()->with('success', __('Mega panel saved. It shows on the website now.'));
    }

    public function destroy(Request $request, Menu $menu, MenuItem $item): RedirectResponse
    {
        $this->check($menu, $item);
        $this->menus->removePanel($request->user(), $item);

        return redirect()->route('admin.menus.edit', $menu)->with('success', __('Mega panel removed. The item shows its sub-items again.'));
    }

    private function check(Menu $menu, MenuItem $item): void
    {
        abort_unless($item->menu_id === $menu->id && $item->parent_id === null, 404);
    }
}
