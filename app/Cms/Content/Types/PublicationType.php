<?php

namespace App\Cms\Content\Types;

use App\Cms\Content\ContentType;
use App\Enums\MediaKind;
use App\Models\ContentItem;
use App\Models\Publication;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

/**
 * Publications: reports, papers and guides with a date, author, cover (the image), a
 * document to download and/or an external link. Listed newest publication first.
 */
class PublicationType extends ContentType
{
    public function key(): string
    {
        return 'publications';
    }

    public function label(): string
    {
        return 'Publications';
    }

    public function singular(): string
    {
        return 'publication';
    }

    public function icon(): string
    {
        return 'bi-journal-richtext';
    }

    public function modelClass(): string
    {
        return Publication::class;
    }

    public function taxonomy(): ?string
    {
        return 'publication_category';
    }

    public function fields(): array
    {
        return [
            'publication_date' => ['type' => 'date', 'label' => 'Publication date', 'rules' => ['nullable', 'date'], 'section' => 'Publication details'],
            'author_text' => ['type' => 'text', 'label' => 'Author(s)', 'rules' => ['nullable', 'string', 'max:255'], 'placeholder' => 'e.g. Research team, or names', 'section' => 'Publication details'],
            'document_media_id' => [
                'type' => 'media', 'media_kind' => 'document', 'label' => 'Document (PDF…)', 'section' => 'Publication details',
                // Public library documents only: visitors download it from the publication page.
                'rules' => ['nullable', 'integer', Rule::exists('media', 'id')->where('kind', MediaKind::Document->value)->where('disk', config('pacms.media.disk'))],
                'help' => 'Visitors can download it from the publication page. Private files cannot be used.',
            ],
            'external_url' => ['type' => 'url', 'label' => 'External link', 'rules' => ['nullable', 'url:http,https', 'max:1024'], 'help' => 'e.g. the publisher\'s page, if the publication is hosted elsewhere.', 'section' => 'Publication details'],
        ];
    }

    public function listSubtitle(ContentItem $item): ?string
    {
        /** @var Publication $item */
        return trim(implode(' · ', array_filter([$item->publication_date?->format('j F Y'), $item->author_text])));
    }

    public function orders(): array
    {
        return ['latest' => 'Newest publication first', 'oldest' => 'Oldest publication first', 'title' => 'Title (A–Z)'];
    }

    public function applyOrder(Builder $query, string $order): void
    {
        // Undated publications fall back to their website publication date.
        match ($order) {
            'oldest' => $query->orderByRaw('COALESCE(publication_date, DATE(published_at)) asc')->orderBy('id'),
            'title' => $query->orderBy('title'),
            default => $query->orderByRaw('COALESCE(publication_date, DATE(published_at)) desc')->orderByDesc('id'),
        };
    }

    public function toItem(ContentItem $item): array
    {
        /** @var Publication $item */
        $base = parent::toItem($item);
        $base['date'] = ($item->publication_date ?? $item->published_at)?->toIso8601String();
        $base['meta'] = array_filter($base['meta'] + ['author' => $item->author_text]);

        return $base;
    }

    public function details(ContentItem $item): array
    {
        /** @var Publication $item */
        $item->loadMissing('document');
        $document = $item->document?->isPublic() ? $item->document : null;
        $actions = [];
        if ($document !== null) {
            $actions[] = [
                'label' => 'Download '.strtoupper((string) $document->extension),
                'url' => $document->url(),
                'icon' => 'bi-download',
                'external' => false,
                'download' => true,
                'meta' => $document->humanSize(),
            ];
        }
        if ($item->external_url) {
            $actions[] = ['label' => 'Read online', 'url' => $item->external_url, 'icon' => 'bi-box-arrow-up-right', 'external' => true];
        }

        return [
            'facts' => array_values(array_filter([
                ['icon' => 'bi-calendar3', 'label' => 'Published', 'value' => $item->publication_date?->format('j F Y')],
                ['icon' => 'bi-person', 'label' => 'Author', 'value' => $item->author_text],
            ], fn (array $fact) => $fact['value'] !== null && $fact['value'] !== '')),
            'actions' => $actions,
        ];
    }

    public function jsonLd(ContentItem $item, array $seo, string $siteName): ?array
    {
        /** @var Publication $item */
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'CreativeWork',
            'name' => $item->title,
            'description' => $seo['description'] ?? null,
            'datePublished' => $item->publication_date?->toDateString() ?? $item->published_at?->toDateString(),
            'author' => $item->author_text ? ['@type' => 'Person', 'name' => $item->author_text] : ['@type' => 'Organization', 'name' => $siteName],
            'publisher' => ['@type' => 'Organization', 'name' => $siteName],
            'image' => $seo['og']['image'] ?? null,
            'url' => $seo['canonical'] ?? null,
        ]);
    }
}
