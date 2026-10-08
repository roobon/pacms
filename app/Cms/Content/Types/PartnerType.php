<?php

namespace App\Cms\Content\Types;

use App\Cms\Content\ContentType;
use App\Models\ContentItem;
use App\Models\Partner;
use Illuminate\Database\Eloquent\Builder;

/**
 * Partners: organisations with a logo, description, website and category, in a chosen
 * order. No pages of their own: cards link to the partner's website. Managed with
 * partners.manage; active or inactive.
 */
class PartnerType extends ContentType
{
    public function key(): string
    {
        return 'partners';
    }

    public function label(): string
    {
        return 'Partners';
    }

    public function singular(): string
    {
        return 'partner';
    }

    public function icon(): string
    {
        return 'bi-building';
    }

    public function modelClass(): string
    {
        return Partner::class;
    }

    public function taxonomy(): ?string
    {
        return 'partner_category';
    }

    public function managePermission(): ?string
    {
        return 'partners.manage';
    }

    public function hasDetailPages(): bool
    {
        return false;
    }

    public function positioned(): bool
    {
        return true;
    }

    public function labels(): array
    {
        return ['title' => 'Organisation', 'excerpt' => 'Short description', 'body' => 'Description', 'image' => 'Logo'];
    }

    public function fields(): array
    {
        return [
            'website_url' => ['type' => 'url', 'label' => 'Website', 'rules' => ['nullable', 'url:http,https', 'max:1024'], 'help' => 'Partner cards and logos link here.', 'section' => 'Partner details'],
            'position' => ['type' => 'number', 'label' => 'Display order', 'rules' => ['nullable', 'integer', 'min:0', 'max:100000'], 'help' => 'Lower numbers come first.', 'section' => 'Partner details'],
        ];
    }

    public function prepare(array $data): array
    {
        $data = parent::prepare($data);
        if (array_key_exists('position', $data)) {
            $data['position'] = (int) ($data['position'] ?? 0);
        }

        return $data;
    }

    public function listSubtitle(ContentItem $item): ?string
    {
        /** @var Partner $item */
        return $item->website_url;
    }

    public function orders(): array
    {
        return ['position' => 'Display order', 'title' => 'Name (A–Z)'];
    }

    public function applyOrder(Builder $query, string $order): void
    {
        match ($order) {
            'title' => $query->orderBy('title'),
            default => $query->orderBy('position')->orderBy('title'),
        };
    }

    public function applyArchiveView(Builder $query, ?string $view): void
    {
        $this->applyOrder($query, 'position');
    }

    public function toItem(ContentItem $item): array
    {
        /** @var Partner $item */
        $base = parent::toItem($item);
        // No detail page: the card links to the partner's own website (or nowhere).
        $base['url'] = $item->website_url;
        $base['external'] = $item->website_url !== null;
        $base['date'] = null;
        $base['image'] = $item->featuredMedia?->toImageArray('(min-width: 992px) 16vw, 33vw');

        return $base;
    }
}
