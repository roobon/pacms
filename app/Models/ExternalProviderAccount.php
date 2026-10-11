<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Credentials for a provider (a connected Facebook Page). The token is encrypted at rest and
 * never shown again in the admin (CMS-ARCHITECTURE.md §15.2, §17.2).
 *
 * @property int $id
 * @property string $provider
 * @property string $name
 * @property array<string, mixed>|null $credentials
 * @property Carbon|null $token_expires_at
 * @property string $status
 * @property Carbon|null $last_verified_at
 */
class ExternalProviderAccount extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $hidden = ['credentials'];

    protected function casts(): array
    {
        return ['credentials' => 'encrypted:array', 'token_expires_at' => 'datetime', 'last_verified_at' => 'datetime'];
    }

    /** The token expires within a week (the admin is warned). */
    public function expiresSoon(): bool
    {
        return $this->token_expires_at !== null && $this->token_expires_at->isBefore(now()->addDays(7));
    }
}
