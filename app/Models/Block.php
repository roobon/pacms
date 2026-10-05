<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One node of a block tree (CMS-ARCHITECTURE.md §5.1). Trees are read and written as a
 * whole through App\Cms\Blocks\BlockTreeRepository; never edit rows individually.
 */
class Block extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'content' => 'array',
            'source' => 'array',
            'display' => 'array',
            'layout' => 'array',
            'style' => 'array',
            'responsive' => 'array',
            'advanced' => 'array',
            'is_hidden' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<BlockType, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(BlockType::class, 'block_type_id');
    }
}
