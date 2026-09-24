<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Redirect extends Model
{
    public const STATUS_CODES = [301, 302, 307, 308];

    protected $fillable = ['source_path', 'target_path', 'status_code', 'is_auto', 'created_by'];

    protected function casts(): array
    {
        return [
            'is_auto' => 'boolean',
            'last_hit_at' => 'datetime',
        ];
    }

    /**
     * Normalises a site path to "/segment/segment" (no trailing slash, no query).
     */
    public static function normalizePath(string $path): string
    {
        $path = parse_url($path, PHP_URL_PATH) ?: '/';
        $path = '/'.trim(rawurldecode($path), '/');

        return mb_strtolower($path);
    }
}
