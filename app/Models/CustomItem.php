<?php

namespace App\Models;

use App\Cms\Content\ContentTypeRegistry;
use App\Cms\Content\Types\AdminContentType;
use LogicException;

/**
 * An item of a content type made in the admin (Phase 8D). Items of every such type share
 * this model and the `custom_items` table; the type's own fields are stored in the JSON
 * column `fields` but read and written like ordinary attributes, so the content engine
 * (forms, revisions, snapshots, payloads) treats them like any module's columns.
 *
 * @property int $content_type_id
 * @property array<string, mixed>|null $fields
 * @property int $position
 */
class CustomItem extends ContentItem
{
    protected $table = 'custom_items';

    protected $fillable = ['title', 'slug', 'excerpt', 'body', 'featured_media_id', 'featured', 'sidebar_mode', 'sidebar_global_block_id', 'position'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['status' => 'draft', 'featured' => false, 'sidebar_mode' => 'default', 'lock_version' => 0, 'position' => 0];

    /** Morph alias; the registry key of an item's type comes from its content_type_id. */
    public static function typeKey(): string
    {
        throw new LogicException('Items of admin-made types take their type from content_type_id.');
    }

    public function contentTypeKey(): string
    {
        return app(ContentTypeRegistry::class)->customKey((int) ($this->attributes['content_type_id'] ?? 0));
    }

    protected function casts(): array
    {
        return parent::casts() + ['fields' => 'array', 'position' => 'integer'];
    }

    /**
     * The type's own fields read from the JSON column.
     */
    public function getAttribute($key)
    {
        if (is_string($key) && $this->isCustomField($key)) {
            return $this->customValues()[$key] ?? null;
        }

        return parent::getAttribute($key);
    }

    /**
     * The type's own fields written into the JSON column.
     */
    public function setAttribute($key, $value)
    {
        if (is_string($key) && $this->isCustomField($key)) {
            $values = $this->customValues();
            $values[$key] = $value;

            return parent::setAttribute('fields', $values);
        }

        return parent::setAttribute($key, $value);
    }

    /**
     * @return array<string, mixed>
     */
    private function customValues(): array
    {
        $raw = $this->attributes['fields'] ?? null;

        return is_array($raw) ? $raw : (is_string($raw) ? (array) json_decode($raw, true) : []);
    }

    private function isCustomField(string $key): bool
    {
        $id = (int) ($this->attributes['content_type_id'] ?? 0);
        if ($id === 0) {
            return false;
        }
        $type = app(ContentTypeRegistry::class)->findCustom($id);

        return $type instanceof AdminContentType && $type->isCustomField($key);
    }
}
