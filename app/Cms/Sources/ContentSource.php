<?php

namespace App\Cms\Sources;

use App\Cms\Content\ContentType;
use App\Models\ContentItem;

/**
 * Dynamic source over any registered content module (news, events…): published items only,
 * with the filters and orders the module's ContentType declares.
 */
class ContentSource extends DynamicSource
{
    public function __construct(private readonly ContentType $type) {}

    public function entity(): string
    {
        return $this->type->key();
    }

    public function filters(): array
    {
        return $this->type->filters();
    }

    public function orders(): array
    {
        return $this->type->orders();
    }

    public function items(array $config): array
    {
        $model = $this->type->modelClass();
        $query = $model::query()->published()->with(array_filter([
            'featuredMedia',
            $this->type->taxonomy() ? 'terms' : null,
        ]));

        $this->type->applyFilters($query, (array) ($config['filters'] ?? []));
        $this->type->applyOrder($query, (string) ($config['order'] ?? array_key_first($this->orders())));

        return $query->limit((int) ($config['limit'] ?? 6))->get()->map(fn (ContentItem $item) => $item->toItem())->values()->all();
    }
}
