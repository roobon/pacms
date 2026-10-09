<?php

namespace App\Services\Search;

use App\Cms\Blocks\BlockRegistry;
use App\Cms\Blocks\BlockTreeRepository;
use App\Cms\Content\ContentTypeRegistry;
use App\Models\ContentItem;
use App\Models\Page;
use App\Models\Revision;
use App\Models\SearchDocument;
use App\Services\Settings\SettingsService;
use App\Support\Html\HtmlSanitizer;
use Illuminate\Database\Eloquent\Model;

/**
 * Keeps `search_documents` in step with what is live (CMS-ARCHITECTURE.md §21): one row per
 * published page or item, with its title and plain text (summary, article text, module
 * fields and the text of its blocks). Anything not public has no row, so search can never
 * return it.
 *
 * Called after every save (SearchObserver, after the transaction commits) and by
 * pacms:search:rebuild.
 */
class SearchIndexer
{
    /** Text fields that are not page text (screen-reader names, empty-list messages). */
    private const SKIP_KEYS = ['aria_label', 'empty_text'];

    private const MAX_BODY = 60000;

    public function __construct(
        private readonly ContentTypeRegistry $types,
        private readonly BlockTreeRepository $blocks,
        private readonly BlockRegistry $registry,
        private readonly SettingsService $settings,
    ) {}

    public function sync(Model $model): void
    {
        $document = match (true) {
            $model instanceof Page => $this->forPage($model),
            $model instanceof ContentItem => $this->forItem($model),
            default => null,
        };

        if ($document === null) {
            $this->remove($model);

            return;
        }

        SearchDocument::query()->updateOrCreate(
            ['searchable_type' => $model->getMorphClass(), 'searchable_id' => $model->getKey()],
            $document,
        );
    }

    public function remove(Model $model): void
    {
        SearchDocument::query()->where('searchable_type', $model->getMorphClass())->where('searchable_id', $model->getKey())->delete();
    }

    /**
     * Index everything again from scratch. Returns the number of documents.
     */
    public function rebuild(): int
    {
        SearchDocument::query()->delete();
        $count = 0;

        Page::query()->live()->each(function (Page $page) use (&$count) {
            $this->sync($page);
            $count++;
        });

        foreach ($this->types->all() as $type) {
            if (! $type->searchable()) {
                continue;
            }
            $type->query()->published()->each(function (ContentItem $item) use (&$count) {
                $this->sync($item);
                $count++;
            });
        }

        return $count;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function forPage(Page $page): ?array
    {
        if ($page->trashed() || ! $page->isLive()) {
            return null;
        }

        // The live version is the published snapshot, not the working copy being edited.
        $revision = Revision::query()->find($page->published_revision_id);
        $snapshot = $revision !== null ? (array) $revision->snapshot : [];
        $fields = (array) ($snapshot['fields'] ?? []);

        // Pages kept out of search engines stay out of the site search too.
        if ($page->loadMissing('seo')->seo?->robots_index === false) {
            return null;
        }

        return [
            'type' => 'pages',
            'title' => mb_substr((string) ($fields['title'] ?? $page->title), 0, 512),
            'body' => $this->text([
                HtmlSanitizer::toText((string) ($fields['excerpt'] ?? $page->excerpt ?? '')),
                $this->blockText((array) ($snapshot['blocks'] ?? [])),
            ]),
            // The home page is found at the site root.
            'url' => (int) $this->settings->get('site', 'homepage_page_id') === $page->id ? '/' : (string) $page->publicUrl(),
            'published_at' => $page->published_at,
            // Pages are the site's main entry points.
            'boost' => 2,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function forItem(ContentItem $item): ?array
    {
        $type = $item->type();
        if (! $type->searchable() || ! $item->isPublished() || $item->loadMissing('seo')->seo?->robots_index === false) {
            return null;
        }

        return [
            'type' => $type->key(),
            'title' => mb_substr($item->title, 0, 512),
            'body' => $this->text([
                HtmlSanitizer::toText((string) $item->excerpt),
                HtmlSanitizer::toText((string) $item->body),
                $type->searchText($item),
                $this->blockText($this->blocks->load($item)),
            ]),
            'url' => $item->url(),
            'published_at' => $item->published_at,
            'boost' => 1,
        ];
    }

    /**
     * Readable text of a block tree: the values of each block's text, textarea and rich-text
     * fields (headings, text, list items, button labels, quotes…), in order.
     *
     * @param  list<array<string, mixed>>  $nodes
     */
    public function blockText(array $nodes): string
    {
        $parts = [];
        foreach ($nodes as $node) {
            if (! is_array($node) || ! empty($node['hidden'])) {
                continue;
            }
            $type = $this->registry->find((string) ($node['type'] ?? ''));
            if ($type !== null) {
                $this->collect(array_map(fn ($field) => $field->toArray(), $type->fields()), (array) ($node['content'] ?? []), $parts);
            }
            if (! empty($node['children'])) {
                $parts[] = $this->blockText((array) $node['children']);
            }
        }

        return implode("\n", array_filter($parts));
    }

    /**
     * @param  list<array<string, mixed>>  $fields  field definitions
     * @param  array<string, mixed>  $values
     * @param  list<string>  $parts
     */
    private function collect(array $fields, array $values, array &$parts): void
    {
        foreach ($fields as $field) {
            $value = $values[$field['key']] ?? null;
            if ($value === null || in_array($field['key'], self::SKIP_KEYS, true)) {
                continue;
            }
            $type = $field['type'];
            if (in_array($type, ['text', 'textarea'], true) && is_string($value)) {
                $parts[] = $value;
            } elseif ($type === 'rich-text' && is_string($value)) {
                $parts[] = HtmlSanitizer::toText($value) ?? '';
            } elseif ($type === 'link' && is_array($value) && is_string($value['label'] ?? null)) {
                $parts[] = $value['label'];
            } elseif ($type === 'repeater' && is_array($value)) {
                foreach ($value as $row) {
                    if (is_array($row)) {
                        $this->collect((array) $field['fields'], $row, $parts);
                    }
                }
            }
        }
    }

    /**
     * @param  list<string>  $parts
     */
    private function text(array $parts): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', implode("\n", array_filter($parts))));

        return mb_substr($text, 0, self::MAX_BODY);
    }
}
