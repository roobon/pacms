<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One live page or item in the site search (CMS-ARCHITECTURE.md §21). Written only by
 * SearchIndexer.
 *
 * @property int $id
 * @property string $searchable_type
 * @property int $searchable_id
 * @property string $type
 * @property string $title
 * @property string|null $body
 * @property string $url
 * @property Carbon|null $published_at
 * @property int $boost
 */
class SearchDocument extends Model
{
    protected $fillable = ['searchable_type', 'searchable_id', 'type', 'title', 'body', 'url', 'published_at', 'boost'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'boost' => 'integer'];
    }
}
