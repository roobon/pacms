<?php

namespace App\Models;

use App\Services\ActivityLog\ActivityLogger;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A piece of press coverage (Phase 8C.2): an article, broadcast or interview about the
 * organisation, with a link to the original and optionally an archived PDF or video.
 * The original link is checked on a schedule (CMS-ARCHITECTURE.md §19); the archive is
 * shown publicly only when an editor has confirmed the rights. See MediaCoverageType.
 *
 * @property string|null $source_name
 * @property string|null $source_url
 * @property string $coverage_type
 * @property Carbon|null $publication_date
 * @property int|null $archive_pdf_media_id
 * @property int|null $archive_video_media_id
 * @property bool $archive_rights_confirmed
 * @property string|null $archive_rights_note
 * @property string $availability
 * @property string $availability_override
 * @property Carbon|null $last_checked_at
 * @property int|null $http_status
 * @property string|null $last_check_error
 * @property int $consecutive_failures
 * @property Carbon|null $next_check_at
 */
class MediaCoverage extends ContentItem
{
    protected $table = 'media_coverage';

    protected $fillable = [
        'title', 'slug', 'excerpt', 'body', 'featured_media_id', 'featured', 'sidebar_mode', 'sidebar_global_block_id',
        'source_name', 'source_url', 'coverage_type', 'publication_date', 'archive_pdf_media_id', 'archive_video_media_id',
        'archive_rights_confirmed', 'archive_rights_note', 'availability_override',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft', 'featured' => false, 'sidebar_mode' => 'default', 'lock_version' => 0,
        'coverage_type' => 'online', 'archive_rights_confirmed' => false, 'availability' => 'unknown',
        'availability_override' => 'auto', 'consecutive_failures' => 0,
    ];

    public static function typeKey(): string
    {
        return 'media_coverage';
    }

    protected static function booted(): void
    {
        // Confirming (or withdrawing) the right to show an archive is a legal decision: log who did it.
        static::updated(function (MediaCoverage $item) {
            if ($item->wasChanged('archive_rights_confirmed')) {
                app(ActivityLogger::class)->log(
                    $item->archive_rights_confirmed ? 'media_coverage.archive_rights_confirmed' : 'media_coverage.archive_rights_withdrawn',
                    $item,
                    array_filter(['note' => $item->archive_rights_note ? mb_substr($item->archive_rights_note, 0, 1000) : null]),
                );
            }
        });
    }

    protected function casts(): array
    {
        return parent::casts() + [
            'publication_date' => 'date',
            'archive_rights_confirmed' => 'boolean',
            'last_checked_at' => 'datetime',
            'next_check_at' => 'datetime',
            'http_status' => 'integer',
            'consecutive_failures' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Media, $this>
     */
    public function archivePdf(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'archive_pdf_media_id');
    }

    /**
     * @return BelongsTo<Media, $this>
     */
    public function archiveVideo(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'archive_video_media_id');
    }
}
