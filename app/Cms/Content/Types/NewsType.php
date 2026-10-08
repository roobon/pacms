<?php

namespace App\Cms\Content\Types;

use App\Cms\Content\ContentType;
use App\Models\ContentItem;
use App\Models\News;

class NewsType extends ContentType
{
    public function key(): string
    {
        return 'news';
    }

    public function label(): string
    {
        return 'News';
    }

    public function singular(): string
    {
        return 'news item';
    }

    public function icon(): string
    {
        return 'bi-newspaper';
    }

    public function modelClass(): string
    {
        return News::class;
    }

    public function taxonomy(): ?string
    {
        return 'news_category';
    }

    public function showsPublishDate(): bool
    {
        return true;
    }

    public function jsonLd(ContentItem $item, array $seo, string $siteName): ?array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'NewsArticle',
            'headline' => $item->title,
            'datePublished' => $item->published_at?->toIso8601String(),
            'dateModified' => $item->updated_at?->toIso8601String(),
            'image' => $seo['og']['image'] ?? null,
            'mainEntityOfPage' => $seo['canonical'] ?? null,
            'publisher' => ['@type' => 'Organization', 'name' => $siteName],
        ]);
    }
}
