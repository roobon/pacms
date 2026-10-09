<?php

namespace App\Http\Controllers\Admin;

use App\Cms\Content\ContentTypeRegistry;
use App\Http\Controllers\Controller;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Term;
use App\Services\Navigation\MenuService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Design → Menus (Phase 9): the list of menus, and each menu's Menu Builder (a React island
 * that loads and saves the item tree through the JSON endpoints below).
 */
class MenuController extends Controller
{
    public function __construct(private readonly MenuService $menus) {}

    public function index(): View
    {
        return view('admin.menus.index', [
            'menus' => Menu::query()->withCount('items')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:191']]);
        $menu = $this->menus->create($request->user(), $data);

        return redirect()->route('admin.menus.edit', $menu)->with('success', __('Menu ":name" created. Add its items below.', ['name' => $menu->name]));
    }

    public function edit(Menu $menu, ContentTypeRegistry $types): View
    {
        // Categories that can be linked: those of modules with a listing page.
        $categories = [];
        foreach ($types->all() as $type) {
            if ($type->taxonomy() !== null && $type->hasArchive()) {
                foreach (Term::query()->inTaxonomy((string) $type->taxonomy())->orderBy('name')->get(['id', 'name']) as $term) {
                    $categories[] = ['id' => $term->id, 'name' => $term->name, 'group' => $type->label()];
                }
            }
        }

        return view('admin.menus.edit', [
            'menu' => $menu,
            'config' => [
                'menu' => ['id' => $menu->id, 'name' => $menu->name, 'slug' => $menu->slug],
                'endpoints' => [
                    'tree' => route('admin.api.menus.tree', $menu),
                    'targets' => route('admin.api.link-targets'),
                ],
                'types' => MenuItem::TYPES,
                'visibility' => MenuItem::VISIBILITY,
                'categories' => $categories,
                'maxDepth' => Menu::MAX_DEPTH,
            ],
        ]);
    }

    public function update(Request $request, Menu $menu): RedirectResponse
    {
        $this->menus->rename($request->user(), $menu, $request->validate(['name' => ['required', 'string', 'max:191']]));

        return back()->with('success', __('Menu renamed.'));
    }

    public function destroy(Request $request, Menu $menu): RedirectResponse
    {
        $this->menus->delete($request->user(), $menu);

        return redirect()->route('admin.menus.index')->with('success', __('Menu ":name" deleted. Menu blocks that showed it are now empty.', ['name' => $menu->name]));
    }

    /**
     * The item tree for the Menu Builder.
     */
    public function tree(Menu $menu): JsonResponse
    {
        return response()->json(['data' => ['items' => $this->menus->forBuilder($menu), 'lock_version' => $menu->lock_version]]);
    }

    public function saveTree(Request $request, Menu $menu): JsonResponse
    {
        $data = $request->validate([
            'items' => ['present', 'array', 'max:'.MenuService::MAX_ITEMS],
            'lock_version' => ['required', 'integer'],
        ]);
        $menu = $this->menus->saveTree($request->user(), $menu, $data['items'], (int) $data['lock_version']);

        return response()->json(['data' => ['items' => $this->menus->forBuilder($menu), 'lock_version' => $menu->lock_version], 'message' => __('Menu saved.')]);
    }
}
