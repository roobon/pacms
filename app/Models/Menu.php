<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A menu (main, footer, secondary…) edited in Design → Menus (CMS-ARCHITECTURE.md §13.2).
 * Its items form a tree up to MAX_DEPTH levels; Menu blocks in headers, footers and pages
 * show it with every link resolved when rendered (MenuService).
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property int $lock_version
 * @property Carbon|null $updated_at
 */
class Menu extends Model
{
    use SoftDeletes;

    public const MAX_DEPTH = 4;

    protected $fillable = ['name', 'slug', 'location'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['lock_version' => 0];

    /**
     * @return HasMany<MenuItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(MenuItem::class)->orderBy('parent_id')->orderBy('position');
    }
}
