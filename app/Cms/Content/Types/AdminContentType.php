<?php

namespace App\Cms\Content\Types;

use App\Cms\Content\ContentType;
use App\Cms\Fields\VideoUrl;
use App\Enums\MediaKind;
use App\Models\ContentItem;
use App\Models\CustomContentType;
use App\Models\CustomItem;
use App\Models\Media;
use App\Support\Html\HtmlSanitizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * A content type made in the admin (Design → Content types, Phase 8D), built from its
 * CustomContentType row. It gets everything built-in modules have (workflow, revisions,
 * SEO, preview, sidebar, archive and detail pages, search, sitemap) from the engine; what
 * is specific is its fields, built with the field builder:
 *
 * - each field is shown in the details box, as its own section of the page, or not at all;
 * - values are stored in custom_items.fields (CustomItem maps them to attributes).
 */
class AdminContentType extends ContentType
{
    /** Field-builder types a content type may use (a subset of the custom block types). */
    public const FIELD_TYPES = [
        'text', 'textarea', 'rich-text', 'number', 'checkbox', 'select', 'radio', 'multi-select',
        'url', 'email', 'image', 'media', 'date', 'datetime', 'video-url', 'repeater',
    ];

    /** Types that may be used inside a repeater row (shown as plain inputs). */
    public const ROW_TYPES = ['text', 'textarea', 'url', 'email', 'number', 'date'];

    /** Types too large for the details box: always shown as their own section. */
    public const SECTION_ONLY = ['rich-text', 'image', 'media', 'video-url', 'repeater', 'textarea'];

    public const DISPLAYS = ['details' => 'In the details box', 'section' => 'As its own section', 'hidden' => 'Not shown on the page'];

    /** Keys a field may not use: columns and attributes of every item. */
    public const RESERVED_KEYS = [
        'id', 'title', 'slug', 'excerpt', 'body', 'featured_media_id', 'featured', 'sidebar_mode', 'sidebar_global_block_id',
        'fields', 'position', 'status', 'published_at', 'publish_at', 'author_id', 'lock_version', 'created_by', 'updated_by',
        'created_at', 'updated_at', 'deleted_at', 'content_type_id', 'terms', 'seo', 'blocks', 'documents', 'type', 'url',
    ];

    public function __construct(public readonly CustomContentType $definition) {}

    public function key(): string
    {
        return $this->definition->key;
    }

    public function label(): string
    {
        return $this->definition->label;
    }

    public function singular(): string
    {
        return $this->definition->singular;
    }

    public function icon(): string
    {
        return $this->definition->icon;
    }

    public function modelClass(): string
    {
        return CustomItem::class;
    }

    public function routePrefix(): string
    {
        return $this->definition->route_prefix;
    }

    public function query(): Builder
    {
        return parent::query()->where('content_type_id', $this->definition->id);
    }

    public function newItem(): ContentItem
    {
        $item = new CustomItem;
        $item->forceFill(['content_type_id' => $this->definition->id]);

        return $item;
    }

    public function isAdminMade(): bool
    {
        return true;
    }

    public function adminUrl(string $action = 'index', ?ContentItem $item = null, array $parameters = []): string
    {
        return route('admin.types.'.$action, ['type' => $this->key()] + ($item !== null ? ['item' => $item->getKey()] : []) + $parameters);
    }

    public function managePermission(): ?string
    {
        return $this->definition->workflow === 'managed' ? $this->key().'.manage' : null;
    }

    public function positioned(): bool
    {
        return $this->isManaged();
    }

    public function hasArchive(): bool
    {
        return $this->definition->has_archive;
    }

    public function searchable(): bool
    {
        return $this->definition->searchable;
    }

    /** Category taxonomy key of an admin-made type ("ct_researches"). */
    public static function taxonomyFor(string $key): string
    {
        return 'ct_'.$key;
    }

    public function taxonomy(): ?string
    {
        return $this->definition->has_categories ? self::taxonomyFor($this->key()) : null;
    }

    public function documents(): bool
    {
        return $this->definition->has_documents;
    }

    /**
     * The field-builder definitions (validated when the type was saved).
     *
     * @return list<array<string, mixed>>
     */
    public function definitions(): array
    {
        return array_values((array) ($this->definition->fields ?? []));
    }

    public function display(string $key): string
    {
        $definition = collect($this->definitions())->firstWhere('key', $key);
        $display = (string) (($this->definition->display ?? [])[$key] ?? '');
        if (! isset(self::DISPLAYS[$display])) {
            $display = in_array($definition['type'] ?? '', self::SECTION_ONLY, true) ? 'section' : 'details';
        }

        return $display === 'details' && in_array($definition['type'] ?? '', self::SECTION_ONLY, true) ? 'section' : $display;
    }

    public function isCustomField(string $key): bool
    {
        return in_array($key, array_column($this->definitions(), 'key'), true);
    }

    public function fields(): array
    {
        $section = ucfirst($this->singular()).' details';
        $fields = [];
        foreach ($this->definitions() as $definition) {
            $fields[(string) $definition['key']] = $this->formField($definition) + ['section' => $section];
        }
        if ($this->positioned()) {
            $fields['position'] = ['type' => 'number', 'label' => 'Display order', 'rules' => ['nullable', 'integer', 'min:0', 'max:100000'], 'help' => 'Lower numbers come first.', 'section' => 'Display'];
        }

        return $fields;
    }

    /**
     * A field-builder definition as a content form field (type, label, rules…).
     *
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    private function formField(array $definition): array
    {
        $type = (string) $definition['type'];
        $required = ! empty($definition['required']) && $type !== 'checkbox';
        $base = ['label' => (string) $definition['label'], 'help' => $definition['help'] ?? null];
        $presence = $required ? 'required' : 'nullable';
        $options = array_map('strval', array_keys((array) ($definition['options'] ?? [])));

        return $base + match ($type) {
            'text' => ['type' => 'text', 'rules' => [$presence, 'string', 'max:'.(int) ($definition['max'] ?? 255)]],
            'textarea' => ['type' => 'textarea', 'rules' => [$presence, 'string', 'max:'.(int) ($definition['max'] ?? 5000)]],
            'rich-text' => ['type' => 'rich-text', 'rules' => [$presence, 'string', 'max:'.(int) ($definition['max'] ?? 100000)]],
            'number' => ['type' => 'number', 'rules' => array_values(array_filter([$presence, 'numeric', isset($definition['min']) ? 'min:'.$definition['min'] : null, isset($definition['max']) ? 'max:'.$definition['max'] : null]))],
            'checkbox' => ['type' => 'checkbox', 'rules' => ['boolean']],
            'select', 'radio' => ['type' => 'select', 'options' => ['' => '—'] + (array) $definition['options'], 'rules' => [$presence, Rule::in($options)]],
            'multi-select' => ['type' => 'checklist', 'options' => (array) $definition['options'], 'rules' => [$presence, 'array'], 'item_rules' => ['string', Rule::in($options)]],
            'url' => ['type' => 'url', 'rules' => [$presence, 'url:http,https', 'max:'.(int) ($definition['max'] ?? 1024)]],
            'email' => ['type' => 'email', 'rules' => [$presence, 'email', 'max:191']],
            'image' => ['type' => 'media', 'media_kind' => 'image', 'rules' => [$presence, 'integer', Rule::exists('media', 'id')->where('kind', MediaKind::Image->value)->where('disk', config('pacms.media.disk'))]],
            'media' => ['type' => 'media', 'media_kind' => 'document', 'rules' => [$presence, 'integer', Rule::exists('media', 'id')->where('kind', MediaKind::Document->value)->where('disk', config('pacms.media.disk'))]],
            'date' => ['type' => 'date', 'rules' => [$presence, 'date']],
            'datetime' => ['type' => 'datetime', 'rules' => [$presence, 'date']],
            'video-url' => ['type' => 'url', 'placeholder' => 'https://www.youtube.com/watch?v=…', 'rules' => [$presence, 'string', 'max:1024', function (string $attribute, mixed $value, \Closure $fail) {
                if (is_string($value) && $value !== '' && VideoUrl::parse($value) === null) {
                    $fail(__('Use a YouTube or Vimeo link.'));
                }
            }]],
            'repeater' => [
                'type' => 'repeater', 'max' => (int) ($definition['max_items'] ?? 30), 'add_label' => 'Add a row',
                'rules' => [$required ? 'required' : 'nullable', 'array', 'max:'.(int) ($definition['max_items'] ?? 30)],
                'fields' => collect((array) ($definition['fields'] ?? []))->mapWithKeys(fn (array $sub) => [(string) $sub['key'] => [
                    'type' => $sub['type'] === 'textarea' ? 'textarea' : 'text',
                    'label' => (string) $sub['label'],
                    'rules' => match ($sub['type']) {
                        'url' => ['nullable', 'url:http,https', 'max:1024'],
                        'email' => ['nullable', 'email', 'max:191'],
                        'number' => ['nullable', 'numeric'],
                        'date' => ['nullable', 'date'],
                        'textarea' => ['nullable', 'string', 'max:5000'],
                        default => ['nullable', 'string', 'max:500'],
                    },
                ]])->all(),
            ],
            default => ['type' => 'text', 'rules' => ['nullable', 'string', 'max:255']],
        };
    }

    public function prepare(array $data): array
    {
        $html = app(HtmlSanitizer::class);
        foreach ($this->definitions() as $definition) {
            $key = (string) $definition['key'];
            if (! array_key_exists($key, $data)) {
                continue;
            }
            $value = $data[$key];
            $data[$key] = match ($definition['type']) {
                'rich-text' => is_string($value) ? ($html->sanitize($value) ?: null) : null,
                'number' => is_numeric($value) ? $value + 0 : null,
                'multi-select' => array_values(array_filter(array_map('strval', (array) $value))),
                'select', 'radio', 'text', 'textarea', 'url', 'email', 'video-url', 'date', 'datetime' => is_string($value) && trim($value) !== '' ? trim($value) : null,
                default => $value,
            };
        }

        return parent::prepare($data);
    }

    public function searchText(ContentItem $item): string
    {
        $parts = [];
        foreach ($this->definitions() as $definition) {
            if ($this->display((string) $definition['key']) === 'hidden') {
                continue;
            }
            $value = $item->getAttribute((string) $definition['key']);
            $parts[] = match ($definition['type']) {
                'text', 'textarea' => is_string($value) ? $value : null,
                'rich-text' => HtmlSanitizer::toText(is_string($value) ? $value : null),
                'select', 'radio' => (string) (((array) $definition['options'])[$value] ?? ''),
                'repeater' => implode(' ', array_map(fn ($row) => implode(' ', array_filter((array) $row, 'is_string')), (array) $value)),
                default => null,
            };
        }

        return trim(implode(' ', array_filter($parts)));
    }

    public function listSubtitle(ContentItem $item): ?string
    {
        $values = [];
        foreach ($this->definitions() as $definition) {
            if ($this->display((string) $definition['key']) === 'details' && count($values) < 2 && ($text = $this->factValue($definition, $item->getAttribute((string) $definition['key']))) !== null) {
                $values[] = $text;
            }
        }

        return $values === [] ? null : implode(' · ', $values);
    }

    public function orders(): array
    {
        return $this->positioned()
            ? ['position' => 'Display order', 'title' => 'Title (A–Z)', 'latest' => 'Newest first']
            : parent::orders();
    }

    public function applyOrder(Builder $query, string $order): void
    {
        if ($order === 'position') {
            $query->orderBy('position')->orderBy('title');

            return;
        }
        parent::applyOrder($query, $order);
    }

    public function applyArchiveView(Builder $query, ?string $view): void
    {
        $this->applyOrder($query, $this->positioned() ? 'position' : 'latest');
    }

    public function toItem(ContentItem $item): array
    {
        $base = parent::toItem($item);
        // The first value shown in the details box appears on cards too (e.g. a role or a place).
        foreach ($this->definitions() as $definition) {
            if ($this->display((string) $definition['key']) === 'details' && ($text = $this->factValue($definition, $item->getAttribute((string) $definition['key']))) !== null) {
                $base['meta']['role'] = $text;
                break;
            }
        }

        return $base;
    }

    /**
     * Facts (details box) and sections of the detail page, in the order of the fields.
     */
    public function details(ContentItem $item): array
    {
        $facts = [];
        $sections = [];
        foreach ($this->definitions() as $definition) {
            $key = (string) $definition['key'];
            $value = $item->getAttribute($key);
            $display = $this->display($key);
            if ($display === 'hidden' || $value === null || $value === '' || $value === []) {
                continue;
            }
            if ($display === 'details') {
                $text = $this->factValue($definition, $value);
                if ($text !== null) {
                    $facts[] = array_filter([
                        'label' => (string) $definition['label'],
                        'value' => $text,
                        'url' => match ($definition['type']) {
                            'url' => (string) $value,
                            'email' => 'mailto:'.$value,
                            default => null,
                        },
                    ], fn ($v) => $v !== null);
                }

                continue;
            }
            if (($section = $this->section($definition, $value)) !== null) {
                $sections[] = ['key' => $key, 'title' => (string) $definition['label']] + $section;
            }
        }

        return ['facts' => $facts, 'sections' => $sections];
    }

    public function jsonLd(ContentItem $item, array $seo, string $siteName): ?array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'CreativeWork',
            'name' => $item->title,
            'description' => $seo['description'] ?? null,
            'datePublished' => $item->published_at?->toIso8601String(),
            'publisher' => ['@type' => 'Organization', 'name' => $siteName],
            'image' => $seo['og']['image'] ?? null,
            'url' => $seo['canonical'] ?? null,
        ]);
    }

    /**
     * A short text for the details box, or null.
     *
     * @param  array<string, mixed>  $definition
     */
    private function factValue(array $definition, mixed $value): ?string
    {
        if ($value === null || $value === '' || $value === []) {
            return null;
        }
        $options = (array) ($definition['options'] ?? []);

        return match ($definition['type']) {
            'checkbox' => $value ? __('Yes') : null,
            'select', 'radio' => isset($options[$value]) ? (string) $options[$value] : null,
            'multi-select' => implode(', ', array_filter(array_map(fn ($v) => $options[$v] ?? null, (array) $value))) ?: null,
            // Plain digits: numbers here are often years or codes, where "2,025" would be wrong.
            'number' => is_numeric($value) ? (string) ($value + 0) : null,
            'date' => $this->formatDate((string) $value, 'j F Y'),
            'datetime' => $this->formatDate((string) $value, 'j F Y, H:i'),
            'url' => (string) (parse_url((string) $value, PHP_URL_HOST) ?: $value),
            'text', 'email' => (string) $value,
            default => null,
        };
    }

    /**
     * A section of the detail page: rich text, text, an image, a file, a video or rows.
     *
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>|null
     */
    private function section(array $definition, mixed $value): ?array
    {
        switch ($definition['type']) {
            case 'rich-text':
                $html = app(HtmlSanitizer::class)->sanitize((string) $value);

                return $html === '' ? null : ['kind' => 'html', 'html' => $html];
            case 'image':
                $media = Media::query()->find((int) $value);

                return $media !== null && $media->isPublic() ? ['kind' => 'image', 'image' => $media->toImageArray('(min-width: 992px) 800px, 100vw')] : null;
            case 'media':
                $media = Media::query()->find((int) $value);

                return $media !== null && $media->isPublic() ? ['kind' => 'file', 'file' => ['url' => $media->url(), 'name' => $media->original_name, 'size' => $media->humanSize(), 'extension' => strtoupper((string) $media->extension)]] : null;
            case 'video-url':
                $video = VideoUrl::parse((string) $value);

                return $video === null ? null : ['kind' => 'video', 'video' => $video + ['url' => (string) $value]];
            case 'repeater':
                $labels = collect((array) ($definition['fields'] ?? []))->mapWithKeys(fn (array $sub) => [(string) $sub['key'] => (string) $sub['label']])->all();
                // In the order of the row's fields (JSON columns do not keep key order).
                $rows = array_values(array_filter(array_map(function ($row) use ($labels) {
                    $row = (array) $row;
                    $ordered = [];
                    foreach (array_keys($labels) as $key) {
                        if (isset($row[$key]) && $row[$key] !== '') {
                            $ordered[$key] = $row[$key];
                        }
                    }

                    return $ordered;
                }, (array) $value)));

                return $rows === [] ? null : ['kind' => 'rows', 'labels' => $labels, 'rows' => $rows];
            default:
                // Text, long text and anything else shown as a section: plain text with line breaks.
                $text = $this->factValue($definition, $value) ?? (is_string($value) ? $value : null);

                return $text === null || $text === '' ? null : ['kind' => 'text', 'text' => $text];
        }
    }

    private function formatDate(string $value, string $format): ?string
    {
        try {
            return Carbon::parse($value)->format($format);
        } catch (\Throwable) {
            return null;
        }
    }
}
