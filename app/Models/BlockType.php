<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Database record of a block type (synchronised from App\Cms\Blocks\BlockRegistry).
 */
class BlockType extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'fields' => 'array',
            'capabilities' => 'array',
            'defaults' => 'array',
            'is_core' => 'boolean',
        ];
    }
}
