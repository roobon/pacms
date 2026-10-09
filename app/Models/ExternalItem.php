<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An item read from an external source, stored locally (text cleaned on sync).
 *
 * @property int $id
 * @property int $external_source_id
 * @property string $external_id
 * @property string|null $title
 * @property string|null $link
 * @property string|null $excerpt
 * @property string|null $description
 * @property string|null $author
 * @property string|null $category
 * @property string|null $image_url
 * @property Carbon|null $published_at
 */
class ExternalItem extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'source_updated_at' => 'datetime', 'raw_data' => 'array'];
    }

    /**
     * @return BelongsTo<ExternalSource, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(ExternalSource::class, 'external_source_id');
    }
}
