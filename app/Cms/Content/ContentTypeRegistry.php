<?php

namespace App\Cms\Content;

use App\Cms\Content\Types\AdminContentType;
use App\Models\ContentItem;
use App\Models\CustomContentType;
use Illuminate\Database\QueryException;
use InvalidArgumentException;

/**
 * Registered content modules, keyed by type key: the built-in ones from
 * config('pacms.content_types') and the active types made in the admin (content_types
 * table, Phase 8D). Admin-made types are read from the database, never from cached config
 * or routes, so a new type works at once without a deployment.
 */
class ContentTypeRegistry
{
    /** @var array<string, ContentType> */
    private array $types = [];

    /** @var array<int, AdminContentType> content_types.id => type */
    private array $custom = [];

    public function __construct()
    {
        $this->reload();
    }

    /**
     * Read the types again (after a type was created, changed or disabled).
     */
    public function reload(): void
    {
        $this->types = [];
        $this->custom = [];
        foreach (config('pacms.content_types', []) as $class) {
            $type = app($class);
            $this->types[$type->key()] = $type;
        }

        foreach ($this->adminMade() as $definition) {
            $type = new AdminContentType($definition);
            $this->types[$type->key()] ??= $type; // a built-in key always wins
            $this->custom[$definition->id] = $type;
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
        return $this->get($item->contentTypeKey());
    }

    /**
     * An admin-made type by its content_types id (active ones only).
     */
    public function findCustom(int $id): ?AdminContentType
    {
        return $this->custom[$id] ?? null;
    }

    /**
     * Registry key of an admin-made type's items; disabled types have none.
     */
    public function customKey(int $id): string
    {
        return $this->findCustom($id)?->key() ?? throw new InvalidArgumentException("No active content type with id [{$id}].");
    }

    /**
     * Every taxonomy that can be managed: the configured ones (config pacms.taxonomies) and
     * the categories of admin-made types that use them.
     *
     * @return array<string, array{label: string, singular: string, hierarchical: bool}>
     */
    public function taxonomies(): array
    {
        $taxonomies = (array) config('pacms.taxonomies', []);
        foreach ($this->custom as $type) {
            if (($taxonomy = $type->taxonomy()) !== null) {
                $taxonomies[$taxonomy] ??= [
                    'label' => ucfirst($type->singular()).' categories',
                    'singular' => ucfirst($type->singular()).' category',
                    'hierarchical' => true,
                ];
            }
        }

        return $taxonomies;
    }

    /**
     * Model class of every module: key => class (entity links, exports, lookups).
     * Admin-made types share one class; query items through ContentType::query().
     *
     * @return array<string, class-string<ContentItem>>
     */
    public function models(): array
    {
        return array_map(fn (ContentType $type) => $type->modelClass(), $this->types);
    }

    /**
     * @return iterable<CustomContentType>
     */
    private function adminMade(): iterable
    {
        try {
            return CustomContentType::query()->where('is_active', true)->orderBy('position')->orderBy('label')->get();
        } catch (QueryException) {
            // Before the 8D migration has run (fresh installs, upgrades) there are none.
            return [];
        }
    }
}
