<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\MediaCoverage;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves the archived copy (PDF or video) of a published coverage item when it is kept as a
 * private library file (CMS-ARCHITECTURE.md §19). Only with confirmed rights; otherwise the
 * file does not exist for visitors.
 */
class CoverageArchiveController extends Controller
{
    public function __invoke(string $slug, string $kind): StreamedResponse
    {
        $item = MediaCoverage::query()->published()->where('slug', $slug)->firstOrFail();
        abort_unless($item->archive_rights_confirmed, 404);

        $media = $kind === 'pdf' ? $item->archivePdf()->first() : $item->archiveVideo()->first();
        abort_if($media === null || ! Storage::disk($media->disk)->exists($media->path), 404);

        $headers = [
            'Content-Type' => $media->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age=3600',
            'X-Robots-Tag' => 'noindex',
        ];
        // PDFs open in the browser's own viewer, which a sandbox would switch off.
        if ($media->extension !== 'pdf') {
            $headers['Content-Security-Policy'] = "default-src 'none'; media-src 'self'; sandbox";
        }

        return Storage::disk($media->disk)->response($media->path, $media->original_name, $headers, 'inline');
    }
}
