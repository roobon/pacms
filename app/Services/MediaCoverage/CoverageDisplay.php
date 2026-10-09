<?php

namespace App\Services\MediaCoverage;

use App\Models\Media;
use App\Models\MediaCoverage;

/**
 * What a coverage page shows (CMS-ARCHITECTURE.md §19.2): the original link, the archived
 * copy, both, or only the details with a notice. Decided from stored check results only;
 * nothing is checked while a visitor waits.
 *
 * | Original    | Archive   | Shown                                      |
 * | available   | none      | original link                              |
 * | unavailable | PDF/video | archive                                    |
 * | available   | PDF/video | original link and archive                  |
 * | unavailable | none      | details + "original no longer available"   |
 *
 * "Unknown" and "unverified" count as available: many news sites block bots.
 */
class CoverageDisplay
{
    /**
     * @return array{display: string, original_url: string|null, original_available: bool, archive: array<string, array<string, mixed>>, notice: string|null}
     */
    public function decide(MediaCoverage $item): array
    {
        $archive = $this->archive($item);
        $hasOriginal = $item->source_url !== null && $item->source_url !== '';

        $originalWorks = $hasOriginal && match ($item->availability_override) {
            'force_original' => true,
            // Show the archive instead of the original, but never leave the reader with nothing.
            'force_archive' => $archive === [],
            default => $item->availability !== 'unavailable',
        };

        $display = match (true) {
            $originalWorks && $archive !== [] => 'both',
            $originalWorks => 'original',
            $archive !== [] => 'archive',
            default => 'none',
        };

        return [
            'display' => $display,
            'original_url' => $originalWorks ? $item->source_url : null,
            'original_available' => $originalWorks,
            'archive' => $archive,
            'notice' => $hasOriginal && ! $originalWorks
                ? ($archive === [] ? __('The original is no longer available online.') : __('The original is no longer available online. An archived copy is shown instead.'))
                : null,
        ];
    }

    /**
     * Archived copies visitors may see: only with confirmed rights, never otherwise.
     *
     * @return array<string, array<string, mixed>>
     */
    public function archive(MediaCoverage $item): array
    {
        if (! $item->archive_rights_confirmed) {
            return [];
        }

        $item->loadMissing(['archivePdf', 'archiveVideo']);
        $archive = [];
        if ($item->archivePdf !== null) {
            $archive['pdf'] = $this->file($item, $item->archivePdf, 'pdf');
        }
        if ($item->archiveVideo !== null) {
            $archive['video'] = $this->file($item, $item->archiveVideo, 'video');
        }

        return $archive;
    }

    /**
     * @return array<string, mixed>
     */
    private function file(MediaCoverage $item, Media $media, string $kind): array
    {
        return [
            // Private library files are streamed through the coverage page's archive route.
            'url' => $media->url() ?? route('media-coverage.archive', ['slug' => $item->slug, 'kind' => $kind], false),
            'name' => $media->original_name,
            'mime' => $media->mime_type,
            'size' => $media->humanSize(),
        ];
    }
}
