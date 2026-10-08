<?php

namespace App\Cms\Content\Types;

use App\Cms\Content\ContentType;
use App\Models\ContentItem;
use App\Models\TeamMember;
use Illuminate\Database\Eloquent\Builder;

/**
 * Team: people with a designation, department, photo, biography and optional contact
 * details and social links, in a chosen order. Managed with team.manage; active or inactive.
 */
class TeamType extends ContentType
{
    public function key(): string
    {
        return 'team';
    }

    public function label(): string
    {
        return 'Team';
    }

    public function singular(): string
    {
        return 'team member';
    }

    public function icon(): string
    {
        return 'bi-people';
    }

    public function modelClass(): string
    {
        return TeamMember::class;
    }

    public function taxonomy(): ?string
    {
        return 'department';
    }

    public function managePermission(): ?string
    {
        return 'team.manage';
    }

    public function positioned(): bool
    {
        return true;
    }

    public function labels(): array
    {
        return ['title' => 'Name', 'excerpt' => 'Short bio', 'body' => 'Biography', 'image' => 'Photo'];
    }

    public function fields(): array
    {
        return [
            'designation' => ['type' => 'text', 'label' => 'Designation', 'rules' => ['nullable', 'string', 'max:255'], 'placeholder' => 'e.g. Programme Coordinator', 'section' => 'Role & contact'],
            'email' => ['type' => 'email', 'label' => 'E-mail', 'rules' => ['nullable', 'email', 'max:191'], 'section' => 'Role & contact'],
            'show_email' => ['type' => 'checkbox', 'label' => 'Show the e-mail address on the website', 'rules' => ['boolean'], 'section' => 'Role & contact'],
            'phone' => ['type' => 'text', 'label' => 'Phone', 'rules' => ['nullable', 'string', 'max:64'], 'section' => 'Role & contact'],
            'show_phone' => ['type' => 'checkbox', 'label' => 'Show the phone number on the website', 'rules' => ['boolean'], 'section' => 'Role & contact'],
            'social_links' => [
                'type' => 'repeater', 'label' => 'Social links', 'section' => 'Role & contact',
                'rules' => ['nullable', 'array', 'max:10'], 'max' => 10, 'add_label' => 'Add link',
                'fields' => [
                    'network' => ['type' => 'text', 'label' => 'Network (e.g. LinkedIn)', 'rules' => ['nullable', 'string', 'max:40']],
                    'url' => ['type' => 'text', 'label' => 'Address (https://…)', 'rules' => ['nullable', 'url:http,https', 'max:1024']],
                ],
            ],
            'position' => ['type' => 'number', 'label' => 'Display order', 'rules' => ['nullable', 'integer', 'min:0', 'max:100000'], 'help' => 'Lower numbers come first.', 'section' => 'Role & contact'],
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
        /** @var TeamMember $item */
        return $item->designation;
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
        /** @var TeamMember $item */
        $base = parent::toItem($item);
        $base['date'] = null;
        $base['image'] = $item->featuredMedia?->toImageArray('(min-width: 992px) 25vw, 50vw');
        $base['meta'] = array_filter(['role' => $item->designation, 'category' => $base['meta']['category'] ?? null]);

        return $base;
    }

    public function details(ContentItem $item): array
    {
        /** @var TeamMember $item */
        $actions = [];
        if ($item->show_email && $item->email) {
            $actions[] = ['label' => $item->email, 'url' => 'mailto:'.$item->email, 'icon' => 'bi-envelope', 'external' => false];
        }
        if ($item->show_phone && $item->phone) {
            $actions[] = ['label' => $item->phone, 'url' => 'tel:'.preg_replace('/[^0-9+]/', '', $item->phone), 'icon' => 'bi-telephone', 'external' => false];
        }
        foreach ((array) $item->social_links as $link) {
            if (! empty($link['url'])) {
                $actions[] = ['label' => $link['network'] ?: 'Profile', 'url' => $link['url'], 'icon' => 'bi-link-45deg', 'external' => true];
            }
        }

        return [
            'facts' => array_values(array_filter([
                ['icon' => 'bi-person-badge', 'label' => 'Designation', 'value' => $item->designation],
                ['icon' => 'bi-diagram-2', 'label' => 'Department', 'value' => $this->category($item)?->name],
            ], fn (array $fact) => $fact['value'] !== null && $fact['value'] !== '')),
            'actions' => $actions,
        ];
    }

    public function jsonLd(ContentItem $item, array $seo, string $siteName): ?array
    {
        /** @var TeamMember $item */
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Person',
            'name' => $item->title,
            'jobTitle' => $item->designation,
            'image' => $seo['og']['image'] ?? null,
            'worksFor' => ['@type' => 'Organization', 'name' => $siteName],
            'url' => $seo['canonical'] ?? null,
        ]);
    }
}
