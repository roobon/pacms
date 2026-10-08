<?php

namespace App\Cms\Content;

use App\Models\ContentItem;
use InvalidArgumentException;

/**
 * Registered content modules (config('pacms.content_types')), keyed by type key.
 */
class ContentTypeRegistry
{
    /** @var array<string, ContentType> */
    private array $types = [];

    public function __construct()
    {
        foreach (config('pacms.content_types', []) as $class) {
            $type = app($class);
            $this->types[$type->key()] = $type;
        }
    }

    /**
     * @return array<string, ContentType>
     */
    public function all(): array
    {
        return $this->types;
    }

    public function find(string $key): ?ContentType
    {
        return $this->types[$key] ?? null;
    }

    public function get(string $key): ContentType
    {
        return $this->types[$key] ?? throw new InvalidArgumentException("Unknown content type [{$key}].");
    }

    public function forRoutePrefix(string $prefix): ?ContentType
    {
        foreach ($this->types as $type) {
            if ($type->routePrefix() === $prefix) {
                return $type;
            }
        }

        return null;
    }

    public function forModel(ContentItem $item): ContentType
    {
        return $this->get($item::typeKey());
    }

    /**
     * Model class of every module: key => class (entity links, exports, lookups).
     *
     * @return array<string, class-string<ContentItem>>
     */
    public function models(): array
    {
        return array_map(fn (ContentType $type) => $type->modelClass(), $this->types);
    }
}
