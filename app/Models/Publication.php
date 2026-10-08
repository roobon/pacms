<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A publication (Phase 8B): a report, paper or guide with a date, author, cover (the
 * featured image), a document to download and/or an external link. See PublicationType.
 *
 * @property Carbon|null $publication_date
 * @property string|null $author_text
 * @property int|null $document_media_id
 * @property string|null $external_url
 */
class Publication extends ContentItem
{
    protected $fillable = [
        'title', 'slug', 'excerpt', 'body', 'featured_media_id', 'featured', 'sidebar_mode', 'sidebar_global_block_id',
        'publication_date', 'author_text', 'document_media_id', 'external_url',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['status' => 'draft', 'featured' => false, 'sidebar_mode' => 'default', 'lock_version' => 0];

    public static function typeKey(): string
    {
        return 'publications';
    }

    protected function casts(): array
    {
        return parent::casts() + [
            'publication_date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Media, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'document_media_id');
    }
}
