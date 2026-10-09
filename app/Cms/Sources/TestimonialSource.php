<?php

namespace App\Cms\Sources;

use App\Models\Event;
use App\Models\Program;
use App\Models\Project;
use App\Models\Testimonial;

/**
 * Published testimonials for the Testimonials block. Testimonials are not a content module
 * (no pages of their own), so they have their own whitelisted source.
 */
class TestimonialSource extends DynamicSource
{
    public function entity(): string
    {
        return 'testimonials';
    }

    public function filters(): array
    {
        return [
            'featured' => ['type' => 'bool', 'label' => 'Featured only'],
            'program' => ['type' => 'item', 'model' => Program::class, 'label' => 'Program'],
            'project' => ['type' => 'item', 'model' => Project::class, 'label' => 'Project'],
            'event' => ['type' => 'item', 'model' => Event::class, 'label' => 'Event'],
        ];
    }

    public function orders(): array
    {
        return ['position' => 'Display order', 'latest' => 'Newest first', 'random' => 'Random'];
    }

    public function items(array $config): array
    {
        $filters = (array) ($config['filters'] ?? []);
        $query = Testimonial::query()->published()->with('photo')
            ->when(! empty($filters['featured']), fn ($q) => $q->where('featured', true))
            ->when(! empty($filters['program']), fn ($q) => $q->where('program_id', (int) $filters['program']))
            ->when(! empty($filters['project']), fn ($q) => $q->where('project_id', (int) $filters['project']))
            ->when(! empty($filters['event']), fn ($q) => $q->where('event_id', (int) $filters['event']));

        match ($config['order'] ?? 'position') {
            'latest' => $query->orderByDesc('published_at')->orderByDesc('id'),
            'random' => $query->inRandomOrder(),
            default => $query->orderBy('position')->orderByDesc('published_at')->orderByDesc('id'),
        };

        return $query->limit((int) ($config['limit'] ?? 6))->get()->map(fn (Testimonial $item) => $item->toItem())->values()->all();
    }
}
