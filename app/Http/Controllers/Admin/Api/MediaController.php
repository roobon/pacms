<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Admin\MediaController as MediaScreens;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\MediaResource;
use App\Services\Media\MediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * JSON endpoints for the Media Picker island (session auth + CSRF).
 */
class MediaController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        abort_unless($request->user()->can('media.view'), 403);

        $filters = MediaScreens::validateFilters($request);

        return MediaResource::collection(MediaScreens::filteredQuery($filters)->paginate(24)->withQueryString());
    }

    public function store(Request $request, MediaService $media): JsonResponse
    {
        abort_unless($request->user()->can('media.upload'), 403);

        $request->validate([
            'file' => ['required', 'file'],
            'alt' => ['nullable', 'string', 'max:255'],
        ]);

        $item = $media->store($request->file('file'), $request->user(), ['alt' => $request->input('alt')]);

        return (new MediaResource($item))->response()->setStatusCode(201);
    }
}
