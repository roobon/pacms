<?php

namespace App\Cms\Content\Types;

use App\Cms\Content\ContentType;
use App\Models\ContentItem;
use App\Models\Event;
use DateTimeZone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Events: a date range in the event's own time zone, place, organiser and registration
 * link. Listed as upcoming (soonest first) or past (latest first).
 */
class EventType extends ContentType
{
    public function key(): string
    {
        return 'events';
    }

    public function label(): string
    {
        return 'Events';
    }

    public function singular(): string
    {
        return 'event';
    }

    public function icon(): string
    {
        return 'bi-calendar-event';
    }

    public function modelClass(): string
    {
        return Event::class;
    }

    public function taxonomy(): ?string
    {
        return 'event_category';
    }

    public function fields(): array
    {
        return [
            'start_at' => ['type' => 'datetime', 'label' => 'Starts', 'rules' => ['required', 'date'], 'section' => 'When'],
            'end_at' => ['type' => 'datetime', 'label' => 'Ends (optional)', 'rules' => ['nullable', 'date', 'after_or_equal:start_at'], 'section' => 'When'],
            'all_day' => ['type' => 'checkbox', 'label' => 'All-day event (times are not shown)', 'rules' => ['boolean'], 'section' => 'When'],
            'timezone' => ['type' => 'timezone', 'label' => 'Time zone', 'rules' => ['required', Rule::in(DateTimeZone::listIdentifiers())], 'section' => 'When'],
            'venue' => ['type' => 'text', 'label' => 'Venue', 'rules' => ['nullable', 'string', 'max:255'], 'placeholder' => 'e.g. Bangla Academy, Dhaka', 'section' => 'Where'],
            'address' => ['type' => 'textarea', 'label' => 'Address', 'rules' => ['nullable', 'string', 'max:1000'], 'section' => 'Where'],
            'map_url' => ['type' => 'url', 'label' => 'Map link', 'rules' => ['nullable', 'url:http,https', 'max:1024'], 'help' => 'e.g. a Google Maps link.', 'section' => 'Where'],
            'registration_url' => ['type' => 'url', 'label' => 'Registration link', 'rules' => ['nullable', 'url:http,https', 'max:1024'], 'section' => 'Details'],
            'organizer' => ['type' => 'text', 'label' => 'Organiser', 'rules' => ['nullable', 'string', 'max:255'], 'section' => 'Details'],
        ];
    }

    /**
     * Times are entered in the event's time zone and stored in the app's (UTC), so "upcoming"
     * comparisons are always right.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function prepare(array $data): array
    {
        $zone = (string) ($data['timezone'] ?? config('app.timezone'));
        foreach (['start_at', 'end_at'] as $field) {
            if (! empty($data[$field])) {
                $data[$field] = Carbon::parse((string) $data[$field], $zone)->setTimezone((string) config('app.timezone'));
            }
        }
        $data['all_day'] = ! empty($data['all_day']);

        return $data;
    }

    /**
     * Form value of a module field (datetimes shown in the event's time zone).
     */
    public function formValue(ContentItem $item, string $field): mixed
    {
        $value = $item->getAttribute($field);

        return $value instanceof Carbon ? $value->copy()->setTimezone((string) ($item->getAttribute('timezone') ?: config('app.timezone')))->format('Y-m-d\TH:i') : $value;
    }

    public function listSubtitle(ContentItem $item): ?string
    {
        /** @var Event $item */
        return trim($this->dateLabel($item).($item->venue ? ' · '.$item->venue : ''));
    }

    public function orders(): array
    {
        return ['soonest' => 'Soonest first', 'latest' => 'Latest start first', 'title' => 'Title (A–Z)'];
    }

    public function applyOrder(Builder $query, string $order): void
    {
        match ($order) {
            'latest' => $query->orderByDesc('start_at')->orderByDesc('id'),
            'title' => $query->orderBy('title'),
            default => $query->orderBy('start_at')->orderBy('id'),
        };
    }

    public function filters(): array
    {
        return ['when' => ['type' => 'select', 'label' => 'When', 'options' => ['upcoming' => 'Upcoming', 'past' => 'Past', 'all' => 'All']]] + parent::filters();
    }

    public function applyFilters(Builder $query, array $filters): void
    {
        parent::applyFilters($query, $filters);
        $this->when($query, (string) ($filters['when'] ?? 'upcoming'));
    }

    public function archiveViews(): array
    {
        return ['upcoming' => 'Upcoming', 'past' => 'Past'];
    }

    public function applyArchiveView(Builder $query, ?string $view): void
    {
        $view = $view === 'past' ? 'past' : 'upcoming';
        $this->when($query, $view);
        $this->applyOrder($query, $view === 'past' ? 'latest' : 'soonest');
    }

    public function toItem(ContentItem $item): array
    {
        /** @var Event $item */
        $base = parent::toItem($item);
        $base['date'] = $item->start_at->toIso8601String();
        $base['meta'] = array_filter($base['meta'] + [
            'when' => $this->dateLabel($item),
            'place' => $item->venue,
            'status' => $item->isUpcoming() ? null : 'Past event',
        ]);

        return $base;
    }

    public function details(ContentItem $item): array
    {
        /** @var Event $item */
        $zone = $item->timezone ?: (string) config('app.timezone');

        return ['event' => array_filter([
            'start_at' => $item->start_at->copy()->setTimezone($zone)->toIso8601String(),
            'end_at' => $item->end_at?->copy()->setTimezone($zone)->toIso8601String(),
            'all_day' => $item->all_day,
            'timezone' => $zone,
            'when' => $this->dateLabel($item),
            'upcoming' => $item->isUpcoming(),
            'venue' => $item->venue,
            'address' => $item->address,
            'map_url' => $item->map_url,
            'registration_url' => $item->isUpcoming() ? $item->registration_url : null,
            'organizer' => $item->organizer,
        ], fn ($v) => $v !== null && $v !== '')];
    }

    public function jsonLd(ContentItem $item, array $seo, string $siteName): ?array
    {
        /** @var Event $item */
        $zone = $item->timezone ?: (string) config('app.timezone');
        $format = fn (?Carbon $date) => $date === null ? null : ($item->all_day ? $date->copy()->setTimezone($zone)->toDateString() : $date->copy()->setTimezone($zone)->toIso8601String());

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Event',
            'name' => $item->title,
            'startDate' => $format($item->start_at),
            'endDate' => $format($item->end_at),
            'eventStatus' => 'https://schema.org/EventScheduled',
            'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
            'location' => $item->venue || $item->address ? array_filter([
                '@type' => 'Place',
                'name' => $item->venue,
                'address' => $item->address,
            ]) : null,
            'image' => $seo['og']['image'] ?? null,
            'description' => $seo['description'] ?? null,
            'organizer' => ['@type' => 'Organization', 'name' => $item->organizer ?: $siteName],
            'url' => $seo['canonical'] ?? null,
        ]);
    }

    /**
     * "12 October 2026, 10:00–13:00" in the event's time zone.
     */
    public function dateLabel(Event $item): string
    {
        $zone = $item->timezone ?: (string) config('app.timezone');
        $start = $item->start_at->copy()->setTimezone($zone);
        $end = $item->end_at?->copy()->setTimezone($zone);

        if ($item->all_day) {
            return $end && ! $end->isSameDay($start) ? $start->format('j M').' – '.$end->format('j M Y') : $start->format('j F Y');
        }

        if ($end === null) {
            return $start->format('j F Y, H:i');
        }

        return $end->isSameDay($start)
            ? $start->format('j F Y, H:i').'–'.$end->format('H:i')
            : $start->format('j M Y, H:i').' – '.$end->format('j M Y, H:i');
    }

    /**
     * @param  Builder<ContentItem>  $query
     */
    private function when(Builder $query, string $when): void
    {
        $now = now();

        match ($when) {
            'past' => $query->where(fn ($q) => $q->where('end_at', '<', $now)->orWhere(fn ($q) => $q->whereNull('end_at')->where('start_at', '<', $now->copy()->startOfDay()))),
            'all' => null,
            default => $query->where(fn ($q) => $q->where('end_at', '>=', $now)->orWhere(fn ($q) => $q->whereNull('end_at')->where('start_at', '>=', $now->copy()->startOfDay()))),
        };
    }
}
