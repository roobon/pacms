<?php

namespace App\Cms\Content\Types;

use App\Cms\Content\ContentType;
use App\Models\ContentItem;
use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

/**
 * Projects: status (planned, ongoing, completed, paused), dates, location, manager,
 * website and documents. The archive lists all projects or one status.
 */
class ProjectType extends ContentType
{
    public const STATUSES = ['ongoing' => 'Ongoing', 'planned' => 'Planned', 'completed' => 'Completed', 'paused' => 'Paused'];

    public function key(): string
    {
        return 'projects';
    }

    public function label(): string
    {
        return 'Projects';
    }

    public function singular(): string
    {
        return 'project';
    }

    public function icon(): string
    {
        return 'bi-kanban';
    }

    public function modelClass(): string
    {
        return Project::class;
    }

    public function documents(): bool
    {
        return true;
    }

    public function fields(): array
    {
        return [
            'project_status' => ['type' => 'select', 'label' => 'Project status', 'options' => self::STATUSES, 'rules' => ['required', Rule::in(array_keys(self::STATUSES))], 'section' => 'Project details'],
            'start_date' => ['type' => 'date', 'label' => 'Start date', 'rules' => ['nullable', 'date'], 'section' => 'Project details'],
            'end_date' => ['type' => 'date', 'label' => 'End date', 'rules' => ['nullable', 'date', 'after_or_equal:start_date'], 'help' => 'Leave empty while the project is ongoing.', 'section' => 'Project details'],
            'location' => ['type' => 'text', 'label' => 'Location', 'rules' => ['nullable', 'string', 'max:255'], 'placeholder' => 'e.g. Khulna and Satkhira', 'section' => 'Project details'],
            'manager_name' => ['type' => 'text', 'label' => 'Project manager', 'rules' => ['nullable', 'string', 'max:191'], 'section' => 'Project details'],
            'website_url' => ['type' => 'url', 'label' => 'Project website', 'rules' => ['nullable', 'url:http,https', 'max:1024'], 'section' => 'Project details'],
        ];
    }

    public function listSubtitle(ContentItem $item): ?string
    {
        /** @var Project $item */
        return trim(implode(' · ', array_filter([self::STATUSES[$item->project_status] ?? null, $this->period($item), $item->location])));
    }

    public function orders(): array
    {
        return ['latest' => 'Newest first', 'start' => 'Start date (latest first)', 'title' => 'Title (A–Z)'];
    }

    public function applyOrder(Builder $query, string $order): void
    {
        match ($order) {
            'start' => $query->orderByDesc('start_date')->orderByDesc('id'),
            default => parent::applyOrder($query, $order),
        };
    }

    public function filters(): array
    {
        return ['project_status' => ['type' => 'select', 'label' => 'Project status', 'options' => ['all' => 'All'] + self::STATUSES]] + parent::filters();
    }

    public function applyFilters(Builder $query, array $filters): void
    {
        parent::applyFilters($query, $filters);
        $status = (string) ($filters['project_status'] ?? 'all');
        if (isset(self::STATUSES[$status])) {
            $query->where('project_status', $status);
        }
    }

    public function archiveViews(): array
    {
        return ['all' => 'All projects', 'ongoing' => 'Ongoing', 'completed' => 'Completed', 'planned' => 'Planned'];
    }

    public function applyArchiveView(Builder $query, ?string $view): void
    {
        if ($view !== null && isset(self::STATUSES[$view])) {
            $query->where('project_status', $view);
        }
        $this->applyOrder($query, 'latest');
    }

    public function toItem(ContentItem $item): array
    {
        /** @var Project $item */
        $base = parent::toItem($item);
        $base['meta'] = array_filter($base['meta'] + [
            'status' => self::STATUSES[$item->project_status] ?? null,
            'when' => $this->period($item),
            'place' => $item->location,
        ]);

        return $base;
    }

    public function details(ContentItem $item): array
    {
        /** @var Project $item */
        return [
            'facts' => array_values(array_filter([
                ['icon' => 'bi-flag', 'label' => 'Status', 'value' => self::STATUSES[$item->project_status] ?? null],
                ['icon' => 'bi-calendar-range', 'label' => 'Period', 'value' => $this->period($item)],
                ['icon' => 'bi-geo-alt', 'label' => 'Location', 'value' => $item->location],
                ['icon' => 'bi-person', 'label' => 'Project manager', 'value' => $item->manager_name],
            ], fn (array $fact) => $fact['value'] !== null && $fact['value'] !== '')),
            'actions' => $item->website_url ? [['label' => 'Visit the project website', 'url' => $item->website_url, 'icon' => 'bi-box-arrow-up-right', 'external' => true]] : [],
        ];
    }

    public function jsonLd(ContentItem $item, array $seo, string $siteName): ?array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Project',
            'name' => $item->title,
            'description' => $seo['description'] ?? null,
            'image' => $seo['og']['image'] ?? null,
            'url' => $seo['canonical'] ?? null,
            'parentOrganization' => ['@type' => 'Organization', 'name' => $siteName],
        ]);
    }

    /**
     * "March 2024 – June 2026", "Since March 2024" or null.
     */
    public function period(Project $item): ?string
    {
        $start = $item->start_date?->format('F Y');
        $end = $item->end_date?->format('F Y');

        return match (true) {
            $start !== null && $end !== null => $start === $end ? $start : $start.' – '.$end,
            $start !== null => 'Since '.$start,
            $end !== null => 'Until '.$end,
            default => null,
        };
    }
}
