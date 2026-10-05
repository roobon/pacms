<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * "Where is X used?" — rebuilt for an owner every time it is saved (DATABASE-ARCHITECTURE.md §5).
 */
class ContentReference extends Model
{
    public $timestamps = false;

    protected $fillable = ['owner_type', 'owner_id', 'block_uuid', 'target_type', 'target_id', 'context'];

    /**
     * @return MorphTo<Model, $this>
     */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }
}
