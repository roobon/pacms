<?php

namespace App\Support\Admin;

use App\Cms\Content\ContentType;
use App\Cms\Content\ContentTypeRegistry;
use App\Enums\TestimonialStatus;
use App\Models\Testimonial;
use App\Models\User;

/**
 * Admin sidebar definition (CMS-ARCHITECTURE.md §23.2). Items appear only when the
 * user holds the permission; the routes enforce the same permission server-side.
 * Sections for later phases are added here as their screens are built.
 */
final class AdminNavigation
{
    /**
     * @return list<array{label: string, items: list<array<string, mixed>>}>
     */
    public static function for(User $user): array
    {
        $sections = [
            ['label' => '', 'items' => [
                ['label' => 'Dashboard', 'route' => 'admin.dashboard', 'icon' => 'bi-speedometer2', 'active' => 'admin.dashboard', 'can' => 'admin.access'],
            ]],
            // Folding groups keep the menu short however many content types there are (8D.2).
            ['label' => 'Content', 'items' => [
                ['label' => 'Pages', 'route' => 'admin.pages.index', 'icon' => 'bi-file-earmark-richtext', 'active' => 'admin.pages.*', 'can' => 'pages.view'],
                ['group' => 'modules', 'label' => 'Modules', 'icon' => 'bi-grid', 'items' => [
                    ...self::contentModules(adminMade: false),
                    ['label' => 'Testimonials', 'route' => 'admin.testimonials.index', 'icon' => 'bi-chat-quote', 'active' => 'admin.testimonials.*', 'can' => 'testimonials.view',
                        'badge' => $user->can('testimonials.view') ? self::moderationQueue() : null, 'badge_label' => 'waiting for moderation'],
                ]],
                ['group' => 'types', 'label' => 'Your content types', 'icon' => 'bi-collection', 'items' => self::contentModules(adminMade: true)],
                ['group' => 'terms', 'label' => 'Categories and tags', 'icon' => 'bi-bookmarks', 'items' => [
                    ...self::contentCategories(),
                    ['label' => 'Tags', 'route' => 'admin.terms.index', 'params' => ['taxonomy' => 'tag'], 'icon' => 'bi-tags', 'can' => 'taxonomies.manage'],
                ]],
            ]],
            ['label' => 'Media', 'items' => [
                ['label' => 'Library', 'route' => 'admin.media.index', 'icon' => 'bi-images', 'active' => 'admin.media.*', 'can' => 'media.view'],
                ['label' => 'Media categories', 'route' => 'admin.terms.index', 'params' => ['taxonomy' => 'media_category'], 'icon' => 'bi-folder2', 'can' => 'taxonomies.manage'],
            ]],
            ['label' => 'Tools', 'items' => [
                ['label' => 'Import JSON', 'route' => 'admin.import.index', 'icon' => 'bi-filetype-json', 'active' => 'admin.import.*', 'can' => 'import.run'],
            ]],
            ['label' => 'SEO', 'items' => [
                ['label' => 'Redirects', 'route' => 'admin.redirects.index', 'icon' => 'bi-signpost-split', 'active' => 'admin.redirects.*', 'can' => 'redirects.manage'],
            ]],
            ['label' => 'Design', 'items' => [
                ['label' => 'Global blocks', 'route' => 'admin.global-blocks.index', 'icon' => 'bi-globe2', 'active' => 'admin.global-blocks.*', 'can' => 'global_blocks.manage'],
                ['label' => 'Templates', 'route' => 'admin.block-templates.index', 'icon' => 'bi-layout-wtf', 'active' => 'admin.block-templates.*', 'can' => 'templates.manage'],
                ['label' => 'Custom blocks', 'route' => 'admin.block-types.index', 'icon' => 'bi-puzzle', 'active' => 'admin.block-types.*', 'can' => 'block_types.manage'],
                ['label' => 'Content types', 'route' => 'admin.content-types.index', 'icon' => 'bi-collection', 'active' => 'admin.content-types.*', 'can' => 'content_types.manage'],
                ['label' => 'Design Tokens', 'route' => 'admin.design.tokens', 'icon' => 'bi-palette', 'active' => 'admin.design.*', 'can' => 'design_tokens.manage'],
            ]],
            ['label' => 'System', 'items' => [
                ['label' => 'Users', 'route' => 'admin.users.index', 'icon' => 'bi-people', 'active' => 'admin.users.*', 'can' => 'users.view'],
                ['label' => 'Roles & Permissions', 'route' => 'admin.roles.index', 'icon' => 'bi-shield-lock', 'active' => 'admin.roles.*', 'can' => 'users.manage_roles'],
                ['label' => 'Settings', 'route' => 'admin.settings.general', 'icon' => 'bi-gear', 'active' => 'admin.settings.*', 'can' => 'settings.manage'],
                ['label' => 'Activity Log', 'route' => 'admin.activity.index', 'icon' => 'bi-journal-text', 'active' => 'admin.activity.*', 'can' => 'activity_log.view'],
            ]],
        ];

        $visible = [];
        foreach ($sections as $section) {
            $items = self::visible($section['items'], $user);
            if ($items !== []) {
                $visible[] = ['label' => $section['label'], 'items' => $items];
            }
        }

        return $visible;
    }

    /**
     * Entries the user may open, each with its URL and whether it is the current page; a
     * group is kept when one of its entries is, and is open when it holds the current page.
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private static function visible(array $items, User $user): array
    {
        $visible = [];
        foreach ($items as $item) {
            if (isset($item['group'])) {
                $children = self::visible($item['items'], $user);
                if ($children !== []) {
                    $visible[] = ['items' => $children, 'active' => in_array(true, array_column($children, 'active'), true)] + $item;
                }

                continue;
            }
            if (! $user->can($item['can'])) {
                continue;
            }
            $url = $item['url'] ?? route($item['route'], $item['params'] ?? []);
            $current = url()->current();
            $visible[] = ['url' => $url, 'active' => match (true) {
                // Content types: their list and everything below it.
                isset($item['route']) && ! isset($item['params']) => request()->routeIs($item['active']),
                isset($item['params']) => $current === $url,
                default => $current === $url || str_starts_with($current, $url.'/'),
            }] + $item;
        }

        return $visible;
    }

    /**
     * Testimonials waiting for a moderator (the badge on the menu entry).
     */
    private static function moderationQueue(): ?int
    {
        $count = Testimonial::query()->whereIn('status', TestimonialStatus::awaitingModeration())->count();

        return $count > 0 ? $count : null;
    }

    /**
     * One entry per content module: the built-in ones (news, events…) or those made in the admin.
     *
     * @return list<array<string, mixed>>
     */
    private static function contentModules(bool $adminMade): array
    {
        $types = array_filter(app(ContentTypeRegistry::class)->all(), fn (ContentType $type) => $type->isAdminMade() === $adminMade);

        return array_values(array_map(fn (ContentType $type) => [
            'label' => $type->label(),
            'url' => $type->adminUrl(),
            'icon' => $type->icon(),
            'can' => $type->ability('view'),
        ], $types));
    }

    /**
     * The category list of every module that has one.
     *
     * @return list<array<string, mixed>>
     */
    private static function contentCategories(): array
    {
        $registry = app(ContentTypeRegistry::class);
        $definitions = $registry->taxonomies();
        $types = array_filter($registry->all(), fn (ContentType $type) => $type->taxonomy() !== null && $type->taxonomy() !== 'tag'); // Tags has its own entry

        return array_values(array_map(fn (ContentType $type) => [
            'label' => (string) ($definitions[$type->taxonomy()]['label'] ?? $type->taxonomy()),
            'route' => 'admin.terms.index',
            'params' => ['taxonomy' => $type->taxonomy()],
            'icon' => 'bi-bookmark',
            'can' => 'taxonomies.manage',
        ], $types));
    }
}
