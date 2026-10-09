<?php

namespace App\Cms\Blocks\Types;

use App\Models\CustomContentType;
use Illuminate\Support\Str;

/**
 * The collection block of a content type made in the admin (Phase 8D.2), e.g. "Researches"
 * under Dynamic. One per type, slug "type/{key}", built from the content_types row. Blocks
 * of a disabled type stay valid on their pages but cannot be added and show nothing.
 */
final class ContentTypeBlock extends ContentCollectionBlock
{
    public const PREFIX = 'type/';

    public function __construct(public readonly CustomContentType $definition) {}

    public static function slugFor(string $key): string
    {
        return self::PREFIX.$key;
    }

    protected function entity(): string
    {
        return $this->definition->key;
    }

    protected function emptyText(): string
    {
        return __('No :items yet.', ['items' => Str::lower($this->definition->label)]);
    }

    protected function sourceDefaults(): array
    {
        return ['order' => $this->definition->workflow === 'managed' ? 'position' : 'latest', 'limit' => 6];
    }

    public function slug(): string
    {
        return self::slugFor($this->definition->key);
    }

    public function label(): string
    {
        return $this->definition->label;
    }

    public function icon(): string
    {
        return $this->definition->icon;
    }

    public function active(): bool
    {
        return $this->definition->is_active;
    }

    public function toArray(): array
    {
        return parent::toArray() + ['insertable' => $this->active()];
    }
}
