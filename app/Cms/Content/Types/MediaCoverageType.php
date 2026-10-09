<?php

namespace App\Cms\Content\Types;

use App\Cms\Content\ContentType;
use App\Enums\MediaKind;
use App\Models\ContentItem;
use App\Models\MediaCoverage;
use App\Models\Program;
use App\Models\Project;
use App\Services\MediaCoverage\CoverageDisplay;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

/**
 * Media coverage (CMS-ARCHITECTURE.md §19): an archive of press coverage, separate from
 * news. Category (relational) and coverage type (fixed list) are kept apart. The original
 * link is checked on a schedule; an archived PDF or video is public only once an editor
 * with the publish permission confirms the rights.
 */
class MediaCoverageType extends ContentType
{
    public const COVERAGE_TYPES = ['newspaper' => 'Newspaper', 'magazine' => 'Magazine', 'tv' => 'TV', 'radio' => 'Radio', 'online' => 'Online', 'other' => 'Other'];

    public const AVAILABILITY = ['unknown' => 'Not checked yet', 'available' => 'Available', 'unverified' => 'Could not be verified', 'unavailable' => 'No longer available'];

    public const OVERRIDES = ['auto' => 'Automatic (from the source check)', 'force_original' => 'Always show the original link', 'force_archive' => 'Show the archived copy instead'];

    public function key(): string
    {
        return 'media_coverage';
    }

    public function label(): string
    {
        return 'Media Coverage';
    }

    public function singular(): string
    {
        return 'coverage item';
    }

    public function icon(): string
    {
        return 'bi-broadcast';
    }

    public function modelClass(): string
    {
        return MediaCoverage::class;
    }

    public function taxonomy(): ?string
    {
        return 'media_coverage_category';
    }

    public function taxonomies(): array
    {
        return parent::taxonomies() + ['tag' => 'Tags'];
    }

    public function labels(): array
    {
        return ['title' => 'Headline', 'excerpt' => 'Summary', 'body' => 'Description', 'image' => 'Image'];
    }

    public function fields(): array
    {
        $archive = fn (MediaKind $kind) => ['nullable', 'integer', Rule::exists('media', 'id')->where('kind', $kind->value)];

        return [
            'source_name' => ['type' => 'text', 'label' => 'Source', 'rules' => ['required', 'string', 'max:191'], 'placeholder' => 'e.g. The Daily Star', 'section' => 'Source'],
            'source_url' => ['type' => 'url', 'label' => 'Link to the original', 'rules' => ['nullable', 'url:http,https', 'max:2048'], 'section' => 'Source',
                'help' => 'Checked automatically once a day. If it stops working, the archived copy is shown instead.'],
            'coverage_type' => ['type' => 'select', 'label' => 'Coverage type', 'options' => self::COVERAGE_TYPES, 'rules' => ['required', Rule::in(array_keys(self::COVERAGE_TYPES))], 'section' => 'Source'],
            'publication_date' => ['type' => 'date', 'label' => 'Publication date', 'rules' => ['nullable', 'date'], 'section' => 'Source'],
            'availability_override' => ['type' => 'select', 'label' => 'Original link', 'options' => self::OVERRIDES, 'rules' => ['required', Rule::in(array_keys(self::OVERRIDES))], 'section' => 'Source'],
            'archive_pdf_media_id' => ['type' => 'media', 'media_kind' => 'document', 'visibility' => 'any', 'label' => 'Archived copy (PDF)', 'rules' => [...$archive(MediaKind::Document), Rule::exists('media', 'id')->where('extension', 'pdf')], 'section' => 'Archive',
                'help' => 'A scan or saved copy of the article. Upload it as a private file.'],
            'archive_video_media_id' => ['type' => 'media', 'media_kind' => 'video', 'visibility' => 'any', 'label' => 'Archived copy (video)', 'rules' => $archive(MediaKind::Video), 'section' => 'Archive',
                'help' => 'A recording of the broadcast (MP4 or WebM).'],
            // Only people who may publish coverage may decide that an archive may be shown.
            'archive_rights_confirmed' => ['type' => 'checkbox', 'label' => 'We have the right to show the archived copy (permission, licence or other legal basis)', 'rules' => ['boolean'], 'ability' => 'publish', 'section' => 'Archive'],
            'archive_rights_note' => ['type' => 'textarea', 'label' => 'Rights note', 'rules' => ['nullable', 'string', 'max:2000'], 'ability' => 'publish', 'section' => 'Archive',
                'help' => 'Internal: who gave permission, the licence, or the legal basis. Never shown on the website.'],
            'program' => ['type' => 'relation', 'target' => 'programs', 'multiple' => false, 'display' => 'fact', 'label' => 'Related program', 'title' => 'Program', 'section' => 'Related'],
            'project' => ['type' => 'relation', 'target' => 'projects', 'multiple' => false, 'display' => 'fact', 'label' => 'Related project', 'title' => 'Project', 'section' => 'Related'],
        ];
    }

    public function adminPanel(): ?string
    {
        return 'admin.media-coverage.source-panel';
    }

    public function searchText(ContentItem $item): string
    {
        /** @var MediaCoverage $item */
        return (string) $item->source_name;
    }

    public function listSubtitle(ContentItem $item): ?string
    {
        /** @var MediaCoverage $item */
        $availability = $item->source_url && in_array($item->availability, ['unavailable', 'unverified'], true)
            ? 'Original: '.strtolower(self::AVAILABILITY[$item->availability])
            : null;

        return trim(implode(' · ', array_filter([$item->source_name, self::COVERAGE_TYPES[$item->coverage_type] ?? null, $item->publication_date?->format('j F Y'), $availability])));
    }

    public function orders(): array
    {
        return ['latest' => 'Newest first', 'oldest' => 'Oldest first', 'title' => 'Headline (A–Z)'];
    }

    public function applyOrder(Builder $query, string $order): void
    {
        // Undated items fall back to their website publication date.
        match ($order) {
            'oldest' => $query->orderByRaw('COALESCE(publication_date, DATE(published_at)) asc')->orderBy('id'),
            'title' => $query->orderBy('title'),
            default => $query->orderByRaw('COALESCE(publication_date, DATE(published_at)) desc')->orderByDesc('id'),
        };
    }

    public function filters(): array
    {
        return parent::filters() + [
            'coverage_type' => ['type' => 'select', 'label' => 'Coverage type', 'options' => self::COVERAGE_TYPES],
            'program' => ['type' => 'item', 'model' => Program::class, 'label' => 'Program'],
            'project' => ['type' => 'item', 'model' => Project::class, 'label' => 'Project'],
        ];
    }

    public function applyFilters(Builder $query, array $filters): void
    {
        parent::applyFilters($query, $filters);

        if (! empty($filters['coverage_type']) && isset(self::COVERAGE_TYPES[$filters['coverage_type']])) {
            $query->where('coverage_type', $filters['coverage_type']);
        }
        foreach (['program' => 'program', 'project' => 'project'] as $filter => $field) {
            if (! empty($filters[$filter])) {
                $query->whereExists(fn ($relations) => $relations->from('content_relations')
                    ->whereColumn('content_relations.owner_id', 'media_coverage.id')
                    ->where('content_relations.owner_type', 'media_coverage')
                    ->where('content_relations.field', $field)
                    ->where('content_relations.related_id', (int) $filters[$filter]));
            }
        }
    }

    public function toItem(ContentItem $item): array
    {
        /** @var MediaCoverage $item */
        $base = parent::toItem($item);
        $base['date'] = ($item->publication_date ?? $item->published_at)?->toIso8601String();
        $base['meta'] = array_filter($base['meta'] + [
            'source' => implode(' · ', array_filter([$item->source_name, self::COVERAGE_TYPES[$item->coverage_type] ?? null])),
        ]);

        return $base;
    }

    public function details(ContentItem $item): array
    {
        /** @var MediaCoverage $item */
        $coverage = app(CoverageDisplay::class)->decide($item);
        $actions = [];
        if ($coverage['original_url'] !== null) {
            $actions[] = ['label' => 'Read the original', 'url' => $coverage['original_url'], 'icon' => 'bi-box-arrow-up-right', 'external' => true];
        }

        return [
            'facts' => array_values(array_filter([
                ['icon' => 'bi-newspaper', 'label' => 'Source', 'value' => $item->source_name],
                ['icon' => 'bi-broadcast', 'label' => 'Type', 'value' => self::COVERAGE_TYPES[$item->coverage_type] ?? null],
                ['icon' => 'bi-calendar3', 'label' => 'Published', 'value' => $item->publication_date?->format('j F Y')],
            ], fn (array $fact) => $fact['value'] !== null && $fact['value'] !== '')),
            'actions' => $actions,
            'coverage' => [
                'display' => $coverage['display'],
                'notice' => $coverage['notice'],
                'archive' => $coverage['archive'],
            ],
        ];
    }

    public function jsonLd(ContentItem $item, array $seo, string $siteName): ?array
    {
        /** @var MediaCoverage $item */
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $item->title,
            'datePublished' => $item->publication_date?->toDateString() ?? $item->published_at?->toDateString(),
            'publisher' => $item->source_name ? ['@type' => 'Organization', 'name' => $item->source_name] : null,
            'about' => ['@type' => 'Organization', 'name' => $siteName],
            'sameAs' => $item->source_url,
            'image' => $seo['og']['image'] ?? null,
            'mainEntityOfPage' => $seo['canonical'] ?? null,
        ]);
    }
}
