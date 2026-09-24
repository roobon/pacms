<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Pages\PagePayloadBuilder;
use App\Services\Public\PathResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v1/resolve?path=/about/team — what the SPA should show for a URL
 * (API-ARCHITECTURE.md §2.1). Same resolver as the server-rendered shell.
 */
class ResolveController extends Controller
{
    public function __invoke(Request $request, PathResolver $resolver, PagePayloadBuilder $pages): JsonResponse
    {
        $data = $request->validate(['path' => ['required', 'string', 'max:512']]);

        $resolved = $resolver->resolve($data['path']);

        return match ($resolved['kind']) {
            'page' => response()->json(['kind' => 'page', 'data' => $pages->forLivePage($resolved['page'])]),
            'home' => response()->json(['kind' => 'home']),
            'redirect' => response()->json([
                'kind' => 'redirect',
                'to' => $resolved['redirect']->target_path,
                'status' => $resolved['redirect']->status_code,
            ]),
            default => response()->json(['kind' => 'not_found', 'message' => 'Not found.', 'code' => 'not_found'], 404),
        };
    }
}
