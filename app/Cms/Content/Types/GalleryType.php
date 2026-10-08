<?php

namespace App\Cms\Content\Types;

use App\Cms\Content\ContentType;
use App\Cms\Fields\VideoUrl;
use App\Models\ContentItem;
use App\Models\Gallery;
use App\Models\GalleryItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

/**
 * Galleries (CMS mode): ordered photos from the Media Library and YouTube/Vimeo videos,
 * each with a caption and credit; a date, location and photographer; links to the event,
 * project or program they belong to. External sources (Facebook, Flickr…) come in Phase 10.
 */
class GalleryType extends ContentType
{
    public const TYPES = ['photo' => 'Photos', 'video' => 'Videos', 'mixed' => 'Photos and videos'];

    public function key(): string
    {
        return 'galleries';
    }

    public function label(): string
    {
        return 'Galleries';
    }

    public function singular(): string
    {
        return 'gallery';
    }

    public function icon(): string
    {
        return 'bi-images';
    }

    public function modelClass(): string
    {
        return Gallery::class;
    }

    public function taxonomy(): ?string
    {
        return 'tag';
    }

    public function labels(): array
    {
        return ['title' => 'Title', 'excerpt' => 'Summary', 'body' => 'Description', 'image' => 'Cover image'];
    }

    public function fields(): array
    {
        return [
            'gallery_items' => ['type' => 'gallery', 'label' => 'Photos and videos', 'section' => 'Photos and videos', 'rules' => ['nullable', 'array', 'max:300']],
            'gallery_type' => ['type' => 'select', 'label' => 'Gallery type', 'options' => self::TYPES, 'rules' => ['required', Rule::in(array_keys(self::TYPES))], 'section' => 'Gallery details'],
            'gallery_date' => ['type' => 'date', 'label' => 'Date', 'rules' => ['nullable', 'date'], 'section' => 'Gallery details'],
            'location' => ['type' => 'text', 'label' => 'Location', 'rules' => ['nullable', 'string', 'max:255'], 'section' => 'Gallery details'],
            'credit' => ['type' => 'text', 'label' => 'Photographer / credit', 'rules' => ['nullable', 'string', 'max:191'], 'section' => 'Gallery details'],
            'event' => ['type' => 'relation', 'target' => 'events', 'multiple' => false, 'display' => 'fact', 'label' => 'Event', 'section' => 'Belongs to'],
            'project' => ['type' => 'relation', 'target' => 'projects', 'multiple' => false, 'display' => 'fact', 'label' => 'Project', 'section' => 'Belongs to'],
            'program' => ['type' => 'relation', 'target' => 'programs', 'multiple' => false, 'display' => 'fact', 'label' => 'Program', 'section' => 'Belongs to'],
        ];
    }

    public function filters(): array
    {
        return ['gallery' => ['type' => 'item', 'model' => Gallery::class, 'label' => 'One gallery']] + parent::filters();
    }

    public function applyFilters(Builder $query, array $filters): void
    {
        parent::applyFilters($query, $filters);
        if (! empty($filters['gallery'])) {
            $query->whereKey((int) $filters['gallery']);
        }
    }

    public function listSubtitle(ContentItem $item): ?string
    {
        /** @var Gallery $item */
        return trim(implode(' · ', array_filter([$item->gallery_date?->format('j F Y'), $item->location, $item->items()->count().' items'])));
    }

    public function toItem(ContentItem $item): array
    {
        /** @var Gallery $item */
        $base = parent::toItem($item);
        $base['date'] = ($item->gallery_date ?? $item->published_at)?->toIso8601String();
        // Without a cover, the first photo stands in.
        if ($base['image'] === null) {
            $first = $item->items()->whereNotNull('media_id')->with('media')->first();
            $base['image'] = $first?->media?->isPublic() ? $first->media->toImageArray('(min-width: 992px) 33vw, 100vw') : null;
        }
        $base['meta'] = array_filter($base['meta'] + ['place' => $item->location]);
        // Its photos and videos, for the Gallery block (which shows one gallery's media).
        $base['media'] = array_slice($this->media($item), 0, 60);

        return $base;
    }

    public function details(ContentItem $item): array
    {
        /** @var Gallery $item */
        return [
            'facts' => array_values(array_filter([
                ['icon' => 'bi-calendar3', 'label' => 'Date', 'value' => $item->gallery_date?->format('j F Y')],
                ['icon' => 'bi-geo-alt', 'label' => 'Location', 'value' => $item->location],
                ['icon' => 'bi-camera', 'label' => 'Photographer / credit', 'value' => $item->credit],
            ], fn (array $fact) => $fact['value'] !== null && $fact['value'] !== '')),
            'gallery' => $this->media($item),
        ];
    }

    /**
     * Public photos and videos of a gallery, in order (private or removed files left out).
     *
     * @return list<array<string, mixed>>
     */
    public function media(Gallery $gallery): array
    {
        $items = [];
        foreach ($gallery->items()->with('media')->get() as $row) {
            /** @var GalleryItem $row */
            if ($row->media !== null) {
                if (! $row->media->isPublic() || ! $row->media->isImage()) {
                    continue;
                }
                $image = $row->media->toImageArray('(min-width: 992px) 25vw, 50vw');
                if ($image !== null && $row->alt_override) {
                    $image['alt'] = $row->alt_override;
                }
                $items[] = ['kind' => 'image', 'image' => $image, 'caption' => $row->caption, 'credit' => $row->credit];
            } elseif ($row->video_url && ($video = VideoUrl::parse($row->video_url)) !== null) {
                $items[] = [
                    'kind' => 'video',
                    'video' => $video + [
                        'url' => $row->video_url,
                        'thumbnail' => $video['provider'] === 'youtube' ? "https://i.ytimg.com/vi/{$video['id']}/hqdefault.jpg" : null,
                    ],
                    'caption' => $row->caption,
                    'credit' => $row->credit,
                ];
            }
        }

        return $items;
    }
}
