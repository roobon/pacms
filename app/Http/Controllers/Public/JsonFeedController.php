<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Term;
use App\Services\Feeds\JsonFeedBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * /feed.json and /feed/{type}.json (JSON Feed 1.1, D-15).
 */
class JsonFeedController extends Controller
{
    public function __construct(private readonly JsonFeedBuilder $feeds) {}

    public function combined(): JsonResponse
    {
        return $this->respond($this->feeds->combined());
    }

    public function module(Request $request, string $module): JsonResponse
    {
        $type = collect($this->feeds->feedTypes())->first(fn ($type) => $type->routePrefix() === $module) ?? abort(404);

        // ?category= only accepts a category of this module; anything else is not found.
        $category = null;
        if ($request->filled('category')) {
            $category = $type->taxonomy() === null ? null : Term::query()->inTaxonomy((string) $type->taxonomy())->where('slug', (string) $request->query('category'))->first();
            abort_if($category === null, 404);
        }

        return $this->respond($this->feeds->forType($type, $category));
    }

    /**
     * @param  array<string, mixed>  $feed
     */
    private function respond(array $feed): JsonResponse
    {
        return response()->json($feed, 200, ['Content-Type' => 'application/feed+json; charset=UTF-8'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
