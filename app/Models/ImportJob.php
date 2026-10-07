<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One JSON import (CMS-BLOCK-SCHEMA.md §17): the translated document, its asset plan and
 * the report, from analysis through confirmation to the created draft.
 *
 * @property array<string, mixed> $report
 * @property array<int, array<string, mixed>>|null $assets
 * @property array<string, mixed>|null $options
 */
class ImportJob extends Model
{
    public const AWAITING = 'awaiting_confirmation';

    public const IMPORTING = 'importing';

    public const COMPLETED = 'completed';

    public const FAILED = 'failed';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'assets' => 'array',
            'report' => 'array',
            'options' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * @return array<string, mixed>
     */
    public function documentData(): array
    {
        return (array) json_decode($this->document, true, 128);
    }

    public function hasErrors(): bool
    {
        foreach ((array) ($this->report['entries'] ?? []) as $entry) {
            if (($entry['level'] ?? null) === 'error') {
                return true;
            }
        }

        return false;
    }
}
