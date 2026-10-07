<?php

namespace App\Support\Admin;

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
            ['label' => 'Content', 'items' => [
                ['label' => 'Pages', 'route' => 'admin.pages.index', 'icon' => 'bi-file-earmark-richtext', 'active' => 'admin.pages.*', 'can' => 'pages.view'],
                ['label' => 'News', 'route' => 'admin.news.index', 'icon' => 'bi-newspaper', 'active' => 'admin.news.*', 'can' => 'news.view'],
                ['label' => 'Import JSON', 'route' => 'admin.import.index', 'icon' => 'bi-filetype-json', 'active' => 'admin.import.*', 'can' => 'import.run'],
                ['label' => 'News categories', 'route' => 'admin.terms.index', 'params' => ['taxonomy' => 'news_category'], 'icon' => 'bi-bookmarks', 'can' => 'taxonomies.manage'],
            ]],
            ['label' => 'Media', 'items' => [
                ['label' => 'Library', 'route' => 'admin.media.index', 'icon' => 'bi-images', 'active' => 'admin.media.*', 'can' => 'media.view'],
                ['label' => 'Media categories', 'route' => 'admin.terms.index', 'params' => ['taxonomy' => 'media_category'], 'icon' => 'bi-folder2', 'active' => 'admin.terms.*', 'can' => 'taxonomies.manage'],
                ['label' => 'Tags', 'route' => 'admin.terms.index', 'params' => ['taxonomy' => 'tag'], 'icon' => 'bi-tags', 'can' => 'taxonomies.manage'],
            ]],
            ['label' => 'SEO', 'items' => [
                ['label' => 'Redirects', 'route' => 'admin.redirects.index', 'icon' => 'bi-signpost-split', 'active' => 'admin.redirects.*', 'can' => 'redirects.manage'],
            ]],
            ['label' => 'Design', 'items' => [
                ['label' => 'Global blocks', 'route' => 'admin.global-blocks.index', 'icon' => 'bi-globe2', 'active' => 'admin.global-blocks.*', 'can' => 'global_blocks.manage'],
                ['label' => 'Templates', 'route' => 'admin.block-templates.index', 'icon' => 'bi-layout-wtf', 'active' => 'admin.block-templates.*', 'can' => 'templates.manage'],
                ['label' => 'Custom blocks', 'route' => 'admin.block-types.index', 'icon' => 'bi-puzzle', 'active' => 'admin.block-types.*', 'can' => 'block_types.manage'],
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
            $items = array_values(array_filter($section['items'], fn (array $item) => $user->can($item['can'])));
            if ($items !== []) {
                $visible[] = ['label' => $section['label'], 'items' => $items];
            }
        }

        return $visible;
    }
}
