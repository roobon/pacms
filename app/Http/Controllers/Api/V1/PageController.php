<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Services\Pages\PagePayloadBuilder;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/v1/pages/{path} — a live page by its public path. Unpublished pages are 404.
 */
class PageController extends Controller
{
    public function __invoke(string $path, PagePayloadBuilder $pages): JsonResponse
    {
        $page = Page::query()->live()->where('published_path', trim(mb_strtolower($path), '/'))->firstOrFail();

        return response()->json(['data' => $pages->forLivePage($page)]);
    }
}
