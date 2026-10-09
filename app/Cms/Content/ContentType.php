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

    /**
     * Items of this type. Built-in modules have a table each; admin-made types share one
     * (custom_items) and scope the query to their type.
     *
     * @return Builder<ContentItem>
     */
    public function query(): Builder
    {
        return $this->modelClass()::query();
    }

    /**
     * A new, unsaved item of this type.
     */
    public function newItem(): ContentItem
    {
        $class = $this->modelClass();

        return new $class;
    }

    /** Created in the admin (Design → Content types) rather than in code. */
    public function isAdminMade(): bool
    {
        return false;
    }

    /**
     * URL of one of this type's admin screens (index, create, edit, update, workflow…).
     *
     * @param  array<string, mixed>  $parameters
     */
    public function adminUrl(string $action = 'index', ?ContentItem $item = null, array $parameters = []): string
    {
        return route('admin.'.$this->key().'.'.$action, ($item !== null ? ['item' => $item->getKey()] : []) + $parameters);
    }

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

    /**
     * One permission that manages everything (e.g. "team.manage"), for simple directory-like
     * modules without editorial workflow; null = the editorial permission set.
     */
    public function managePermission(): ?string
    {
        return null;
    }

    public function isManaged(): bool
    {
        return $this->managePermission() !== null;
    }

    /**
     * The permission for an action (view, create, publish…) on this module.
     */
    public function ability(string $action): string
    {
        return $this->managePermission() ?? $this->permissionKey().'.'.$action;
    }

    /** Whether items have their own public page (/{prefix}/{slug}); partners link out instead. */
    public function hasDetailPages(): bool
    {
        return true;
    }

    /** Whether published items are in the site search (CMS-ARCHITECTURE.md §21). */
    public function searchable(): bool
    {
        return $this->hasDetailPages();
    }

    /**
     * Module text for the search index besides title, summary, article text and blocks
     * (e.g. a team member's designation).
     */
    public function searchText(ContentItem $item): string
    {
        return '';
    }

    /**
     * Blade view of an extra box beside the form (e.g. a coverage item's source check), or null.
     */
    public function adminPanel(): ?string
    {
        return null;
    }

    /** Whether items are ordered by a "Display order" number (team, partners). */
    public function positioned(): bool
    {
        return false;
    }

    /**
     * Admin labels of the common fields, where a module names them differently
     * (team: "Name", "Short bio", "Biography", "Photo").
     *
     * @return array{title: string, excerpt: string, body: string, image: string}
     */
    public function labels(): array
    {
        return ['title' => 'Title', 'excerpt' => 'Summary', 'body' => 'Article text', 'image' => 'Image'];
    }

    /** Category taxonomy, or null. */
    public function taxonomy(): ?string
    {
        return null;
    }

    /**
     * Module-specific form fields: name => [type, label, rules, help?, options?, placeholder?, section?,
     * ability? (only users with this module permission may change it, e.g. 'publish')].
     * Types: text, textarea, url, email, date, datetime, checkbox, select, timezone,
     * media (from the library: 'media_kind' => image|document|video; 'visibility' => 'any'
     * also offers private files, otherwise documents must be public), and repeater
     * (an ordered list of rows: 'fields' => [sub => [type: text|textarea, label, rules]],
     * 'max' => rows, 'add_label' => button text).
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
            // Absent fields keep their value (e.g. fields only some users may change).
            if (! array_key_exists($name, $data)) {
                continue;
            }
            $data[$name] = match ($field['type']) {
                'checkbox' => ! empty($data[$name]),
                'media' => empty($data[$name]) ? null : (int) $data[$name],
                'repeater' => $this->cleanRows((array) $data[$name], $field),
                default => $data[$name],
            };
        }

        return $data;
    }

    /**
     * Value of a module field for the admin form.
     */
    public function formValue(ContentItem $item, string $field): mixed
    {
        $value = $item->getAttribute($field);
        $type = $this->fields()[$field]['type'] ?? null;

        return match (true) {
            $type === 'date' && $value instanceof \DateTimeInterface => $value->format('Y-m-d'),
            $type === 'repeater' => is_array($value) ? $value : [],
            default => $value,
        };
    }

    /**
     * Whether the detail page shows the date it was published on the website (news).
     */
    public function showsPublishDate(): bool
    {
        return false;
    }

    /** Field types stored outside the item's own table (content_relations, gallery_items). */
    public const VIRTUAL_TYPES = ['relation', 'gallery'];

    /**
     * Fields stored in the item's own columns.
     *
     * @return array<string, array<string, mixed>>
     */
    public function columnFields(): array
    {
        return array_filter($this->fields(), fn (array $field) => ! in_array($field['type'], self::VIRTUAL_TYPES, true));
    }

    /**
     * Relation fields: name => definition ('target' => type key, 'multiple' => bool,
     * 'display' => fact|logos|cards|gallery, 'title' => public heading).
     *
     * @return array<string, array<string, mixed>>
     */
    public function relationFields(): array
    {
        return array_filter($this->fields(), fn (array $field) => $field['type'] === 'relation');
    }

    /**
     * Whether items have a "Documents" list (attachments: reports, briefs…).
     */
    public function documents(): bool
    {
        return false;
    }

    /**
     * Repeater rows as saved: known sub-fields only, trimmed, empty rows dropped, re-indexed.
     *
     * @param  array<int|string, mixed>  $rows
     * @param  array<string, mixed>  $field
     * @return list<array<string, string|null>>|null
     */
    protected function cleanRows(array $rows, array $field): ?array
    {
        $clean = [];
        ksort($rows); // validated() can return rows out of their submitted order
        foreach ($rows as $row) {
            $values = [];
            foreach (array_keys($field['fields']) as $sub) {
                $value = trim((string) (is_array($row) ? ($row[$sub] ?? '') : ''));
                $values[$sub] = $value === '' ? null : $value;
            }
            if (array_filter($values, fn ($value) => $value !== null) !== []) {
                $clean[] = $values;
            }
        }

        return $clean === [] ? null : $clean;
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
