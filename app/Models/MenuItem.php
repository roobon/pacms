<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One entry of a menu. Links to pages, content items and categories are stored as their
 * id (linkable) and resolved when rendered, so a changed address never breaks a menu.
 *
 * @property int $id
 * @property int $menu_id
 * @property int|null $parent_id
 * @property int $position
 * @property string $type page|content|term|external_url|custom_url|group
 * @property string|null $label
 * @property string|null $linkable_type
 * @property int|null $linkable_id
 * @property string|null $url
 * @property bool $open_in_new_tab
 * @property string|null $icon
 * @property string|null $css_class
 * @property string $visibility everyone|guests|members
 * @property bool $is_mega
 */
class MenuItem extends Model
{
    public const TYPES = [
        'page' => 'Page',
        'content' => 'Content item',
        'term' => 'Category',
        'external_url' => 'Web address',
        'custom_url' => 'Address on this site',
        'group' => 'Heading (no link)',
    ];

    public const VISIBILITY = ['everyone' => 'Everyone', 'guests' => 'Visitors who are not signed in', 'members' => 'Signed-in members'];

    protected $guarded = ['id'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['visibility' => 'everyone', 'open_in_new_tab' => false, 'is_mega' => false, 'position' => 0];

    protected function casts(): array
    {
        return ['open_in_new_tab' => 'boolean', 'is_mega' => 'boolean', 'position' => 'integer'];
    }

    /**
     * @return BelongsTo<Menu, $this>
     */
    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }
}
