<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

/**
 * One synchronisation attempt of an external source (kept 90 days).
 *
 * @property string $trigger schedule|manual|test
 * @property string $status ok|error
 */
class ExternalSyncLog extends Model
{
    use MassPrunable;

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    /**
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', now()->subDays(90));
    }
}
