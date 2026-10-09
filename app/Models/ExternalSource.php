<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * One configured external source: a feed, a Facebook Page… (CMS-ARCHITECTURE.md §15).
 * Its items are synchronised on a schedule into external_items.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $provider
 * @property array<string, mixed>|null $config
 * @property string|null $format
 * @property string $status enabled|disabled
 * @property int $sync_interval_minutes
 * @property int $max_items
 * @property Carbon|null $last_synced_at
 * @property Carbon|null $last_success_at
 * @property Carbon|null $next_sync_at
 * @property string $last_status never|ok|error
 * @property string|null $last_error
 * @property int $consecutive_failures
 */
class ExternalSource extends Model
{
    use SoftDeletes;

    public const INTERVALS = [15 => 'Every 15 minutes', 30 => 'Every 30 minutes', 60 => 'Every hour', 360 => 'Every 6 hours', 720 => 'Every 12 hours', 1440 => 'Once a day'];

    protected $fillable = ['name', 'description', 'source_website', 'config', 'status', 'sync_interval_minutes', 'max_items'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['status' => 'enabled', 'sync_interval_minutes' => 60, 'max_items' => 50, 'last_status' => 'never', 'consecutive_failures' => 0];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'last_synced_at' => 'datetime',
            'last_success_at' => 'datetime',
            'next_sync_at' => 'datetime',
            'sync_interval_minutes' => 'integer',
            'max_items' => 'integer',
            'consecutive_failures' => 'integer',
        ];
    }

    /**
     * @return HasMany<ExternalItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ExternalItem::class);
    }

    /**
     * @return HasMany<ExternalSyncLog, $this>
     */
    public function logs(): HasMany
    {
        return $this->hasMany(ExternalSyncLog::class)->latest('id');
    }

    /**
     * @param  Builder<ExternalSource>  $query
     */
    public function scopeEnabled(Builder $query): void
    {
        $query->where('status', 'enabled');
    }

    /**
     * @param  Builder<ExternalSource>  $query
     */
    public function scopeDue(Builder $query): void
    {
        $query->enabled()->where(fn ($q) => $q->whereNull('next_sync_at')->orWhere('next_sync_at', '<=', now()));
    }

    public function isEnabled(): bool
    {
        return $this->status === 'enabled';
    }
}
