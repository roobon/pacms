<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A content type made in the admin (Design → Content types, Phase 8D). The registry turns
 * each active one into an AdminContentType; its items are CustomItem rows.
 *
 * @property int $id
 * @property string $key
 * @property string $label
 * @property string $singular
 * @property string $icon
 * @property string $route_prefix
 * @property string $workflow
 * @property list<array<string, mixed>>|null $fields
 * @property array<string, string>|null $display
 * @property bool $has_archive
 * @property bool $searchable
 * @property bool $has_categories
 * @property bool $has_documents
 * @property bool $is_active
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CustomContentType extends Model
{
    protected $table = 'content_types';

    protected $fillable = ['label', 'singular', 'icon', 'route_prefix', 'workflow', 'fields', 'display', 'has_archive', 'searchable', 'has_categories', 'has_documents', 'is_active', 'position'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['workflow' => 'editorial', 'icon' => 'bi-collection', 'has_archive' => true, 'searchable' => true, 'has_categories' => false, 'has_documents' => false, 'is_active' => true, 'position' => 0];

    protected function casts(): array
    {
        return [
            'fields' => 'array',
            'display' => 'array',
            'has_archive' => 'boolean',
            'searchable' => 'boolean',
            'has_categories' => 'boolean',
            'has_documents' => 'boolean',
            'is_active' => 'boolean',
            'position' => 'integer',
        ];
    }

    /**
     * @return HasMany<CustomItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(CustomItem::class, 'content_type_id');
    }
}
