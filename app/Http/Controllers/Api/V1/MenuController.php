<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use App\Services\Navigation\MenuService;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/v1/menus/{slug}: a menu with every link resolved (API-ARCHITECTURE.md §4). Links
 * to unpublished targets are left out; each item says who sees it (everyone, guests,
 * members), and clients hide items accordingly.
 */
class MenuController extends Controller
{
    public function __invoke(string $slug, MenuService $menus): JsonResponse
    {
        abort_unless(Menu::query()->where('slug', $slug)->exists(), 404);

        return response()->json(['data' => ['slug' => $slug, 'items' => $menus->resolve($slug)]]);
    }
}
