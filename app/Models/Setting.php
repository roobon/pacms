<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A single configuration value. Read and write through App\Services\Settings\SettingsService,
 * which caches each group; do not query this model directly from features.
 */
class Setting extends Model
{
    protected $fillable = ['group', 'key', 'value', 'is_public', 'updated_by'];

    protected function casts(): array
    {
        return [
            'value' => 'json',
            'is_public' => 'boolean',
        ];
    }
}
