<?php

namespace App\Cms\Sources;

use App\Models\News;

class NewsSource extends DynamicSource
{
    public function entity(): string
    {
        return 'news';
    }

    public function filters(): array
    {
        return [
            'category' => ['type' => 'term', 'taxonomy' => 'news_category', 'label' => 'Category'],
            'featured' => ['type' => 'bool', 'label' => 'Featured only'],
        ];
    }

    public function orders(): array
    {
        return ['latest' => 'Newest first', 'oldest' => 'Oldest first', 'title' => 'Title (A–Z)'];
    }

    public function items(array $config): array
    {
        $filters = (array) ($config['filters'] ?? []);

        $query = News::query()
            ->published()
            ->with(['featuredMedia', 'terms' => fn ($terms) => $terms->where('taxonomy', 'news_category')])
            ->when($filters['featured'] ?? false, fn ($q) => $q->where('featured', true))
            ->when($filters['category'] ?? null, fn ($q, $term) => $q->whereHas('terms', fn ($t) => $t->whereKey($term)));

        match ($config['order'] ?? 'latest') {
            'oldest' => $query->orderBy('published_at'),
            'title' => $query->orderBy('title'),
            default => $query->orderByDesc('published_at'),
        };

        return $query->limit((int) ($config['limit'] ?? 6))->get()->map(fn (News $news) => $news->toItem())->all();
    }
}
