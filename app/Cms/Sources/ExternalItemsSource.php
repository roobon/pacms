<?php

namespace App\Cms\Sources;

use App\Models\ExternalItem;
use App\Models\ExternalSource;

/**
 * Blocks read external items from local storage only (CMS-ARCHITECTURE.md §15.3): one of
 * these per provider ("feed", later "facebook"), looking sources up by slug.
 */
class ExternalItemsSource implements ExternalProvider
{
    public function __construct(private readonly string $provider) {}

    public function key(): string
    {
        return $this->provider;
    }

    public function hasSource(string $source): bool
    {
        return ExternalSource::query()->where('provider', $this->provider)->where('slug', $source)->exists();
    }

    public function items(string $source, int $limit): array
    {
        $model = ExternalSource::query()->enabled()->where('provider', $this->provider)->where('slug', $source)->first();
        if ($model === null) {
            return []; // a disabled source shows nothing
        }

        return ExternalItem::query()->where('external_source_id', $model->id)
            ->orderByRaw('published_at is null')->orderByDesc('published_at')->orderByDesc('id')
            ->limit(max(1, min(50, $limit)))->get()
            ->map(fn (ExternalItem $item) => [
                'key' => "{$this->provider}:{$item->id}",
                'kind' => 'external',
                'title' => $item->title ?? $item->link ?? '',
                'url' => $item->link,
                'external' => true,
                'excerpt' => $item->excerpt,
                'image' => $item->image_url ? ['src' => $item->image_url, 'alt' => ''] : null,
                'date' => $item->published_at?->toIso8601String(),
                'meta' => array_filter(['source' => $model->name, 'category' => $item->category]),
            ])->all();
    }
}
