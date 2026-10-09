<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Search\SearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * GET /api/v1/search?q=&type[]=&page= (API-ARCHITECTURE.md §3): published pages and items
 * only, paginated, rate limited (30 a minute per IP).
 */
class SearchController extends Controller
{
    public function __invoke(Request $request, SearchService $search): JsonResponse
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
            'type' => ['nullable', 'array', 'max:20'],
            'type.*' => ['string', Rule::in(array_keys($search->types()))],
            'page' => ['nullable', 'integer', 'min:1', 'max:500'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.(int) config('pacms.search.max_per_page')],
        ]);

        $query = SearchService::clean((string) $data['q']);
        if (mb_strlen($query) < 2) {
            return response()->json(['data' => [], 'meta' => ['query' => $query, 'total' => 0, 'current_page' => 1, 'last_page' => 1, 'types' => $search->types()]]);
        }

        $results = $search->search($query, (array) ($data['type'] ?? []), (int) ($data['per_page'] ?? config('pacms.search.per_page')), (int) ($data['page'] ?? 1));

        return response()->json([
            'data' => $results->items(),
            'meta' => [
                'query' => $query,
                'total' => $results->total(),
                'current_page' => $results->currentPage(),
                'last_page' => $results->lastPage(),
                'per_page' => $results->perPage(),
                'types' => $search->types(),
            ],
        ]);
    }
}
