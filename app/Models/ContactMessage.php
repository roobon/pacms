<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A message sent with a Contact form block (Phase 11A). Kept 90 days, then deleted (P-1).
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $subject
 * @property string $message
 * @property string $recipient
 * @property string|null $page_url
 * @property bool $consent
 * @property bool $mailed
 * @property Carbon|null $read_at
 * @property Carbon|null $created_at
 */
class ContactMessage extends Model
{
    use MassPrunable;

    public const RETENTION_DAYS = 90;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['consent' => 'boolean', 'mailed' => 'boolean', 'read_at' => 'datetime'];
    }

    /**
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', now()->subDays(self::RETENTION_DAYS));
    }
}
