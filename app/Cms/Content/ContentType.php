<?php

namespace App\Cms\Content;

use App\Models\ContentItem;
use App\Models\Term;
use App\Support\Html\HtmlSanitizer;
use Illuminate\Database\Eloquent\Builder;

/**
 * Definition of a directly published content module (CMS-ARCHITECTURE.md §3.3, the Content
 * Type Registry). Everything the engine does for every module (admin screens, workflow,
 * revisions, archive and detail pages, collection blocks, sidebars) reads from here, so a
 * new module is a migration, a model, and one of these.
 */
abstract class ContentType
{
    /** Registry key and model morph alias, e.g. "news", "events". */
    abstract public function key(): string;

    /** Plural, as in navigation: "News", "Events". */
    abstract public function label(): string;

    /** One item: "news item", "event". */
    abstract public function singular(): string;

    abstract public function icon(): string;

    /**
     * @return class-string<ContentItem>
     */
    abstract public function modelClass(): string;

    /** Public URL prefix: /news/{slug}. Must be a reserved page slug. */
    public function routePrefix(): string
    {
        return str_replace('_', '-', $this->key());
    }

    /** Permission prefix ({key}.view, .create, .publish…). */
    public function permissionKey(): string
    {
        return $this->key();
    }

    /** Category taxonomy, or null. */
    public function taxonomy(): ?string
    {
        return null;
    }

    /**
     * Module-specific form fields: name => [type, label, rules, help?, options?, placeholder?].
     * Types: text, textarea, url, email, date, datetime, checkbox, select, timezone.
     *
     * @return array<string, array<string, mixed>>
     */
    public function fields(): array
    {
        return [];
    }

    /**
     * Turn validated form input of the module fields into column values (e.g. local time → UTC).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function prepare(array $data): array
    {
        foreach ($this->fields() as $name => $field) {
            if ($field['type'] === 'checkbox') {
                $data[$name] = ! empty($data[$name]);
            }
        }

        return $data;
    }

    /**
     * Value of a module field for the admin form.
     */
    public function formValue(ContentItem $item, string $field): mixed
    {
        return $item->getAttribute($field);
    }

    /**
     * Short line under the title in the admin list (e.g. an event's date).
     */
    public function listSubtitle(ContentItem $item): ?string
    {
        return null;
    }

    /**
     * Sort orders for collection blocks and archives: key => label.
     *
     * @return array<string, string>
     */
    public function orders(): array
    {
        return ['latest' => 'Newest first', 'oldest' => 'Oldest first', 'title' => 'Title (A–Z)'];
    }

    /**
     * @param  Builder<ContentItem>  $query
     */
    public function applyOrder(Builder $query, string $order): void
    {
        match ($order) {
            'oldest' => $query->orderBy('published_at')->orderBy('id'),
            'title' => $query->orderBy('title'),
            default => $query->orderByDesc('published_at')->orderByDesc('id'),
        };
    }

    /**
     * Filters offered to dynamic blocks (SourceRegistry): name => definition.
     *
     * @return array<string, array<string, mixed>>
     */
    public function filters(): array
    {
        $filters = ['featured' => ['type' => 'bool', 'label' => 'Featured only']];

        if ($this->taxonomy() !== null) {
            $filters = ['category' => ['type' => 'term', 'taxonomy' => $this->taxonomy(), 'label' => 'Category']] + $filters;
        }

        return $filters;
    }

    /**
     * @param  Builder<ContentItem>  $query
     * @param  array<string, mixed>  $filters  already whitelisted
     */
    public function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['featured'])) {
            $query->where('featured', true);
        }

        if (! empty($filters['category']) && $this->taxonomy() !== null) {
            $query->whereHas('terms', fn ($terms) => $terms->whereKey((int) $filters['category']));
        }
    }

    /**
     * Normalised item (CMS-ARCHITECTURE.md §6.4) for cards and archives.
     *
     * @return array<string, mixed>
     */
    public function toItem(ContentItem $item): array
    {
        $category = $this->category($item);

        return [
            'key' => $this->key().':'.$item->id,
            'kind' => $this->key(),
            'title' => $item->title,
            'url' => $item->url(),
            'external' => false,
            'excerpt' => HtmlSanitizer::toText($item->excerpt),
            'image' => $item->featuredMedia?->toImageArray('(min-width: 992px) 33vw, 100vw'),
            'date' => $item->published_at?->toIso8601String(),
            'meta' => array_filter(['category' => $category?->name]),
        ];
    }

    /**
     * Extra fields for the public detail payload.
     *
     * @return array<string, mixed>
     */
    public function details(ContentItem $item): array
    {
        return [];
    }

    /**
     * schema.org data for the detail page, or null.
     *
     * @param  array<string, mixed>  $seo  resolved SEO (url, og image…)
     * @return array<string, mixed>|null
     */
    public function jsonLd(ContentItem $item, array $seo, string $siteName): ?array
    {
        return null;
    }

    /**
     * Archive views offered on the public listing (e.g. events: upcoming / past): key => label.
     *
     * @return array<string, string>
     */
    public function archiveViews(): array
    {
        return [];
    }

    /**
     * @param  Builder<ContentItem>  $query
     */
    public function applyArchiveView(Builder $query, ?string $view): void
    {
        $this->applyOrder($query, 'latest');
    }

    protected function category(ContentItem $item): ?Term
    {
        if ($this->taxonomy() === null || ! $item->relationLoaded('terms')) {
            return null;
        }

        return $item->terms->firstWhere('taxonomy', $this->taxonomy());
    }
}
