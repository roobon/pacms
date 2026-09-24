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
     * @return list<array{label: string, items: list<array{label: string, route: string, icon: string, active: string}>}>
     */
    public static function for(User $user): array
    {
        $sections = [
            ['label' => '', 'items' => [
                ['label' => 'Dashboard', 'route' => 'admin.dashboard', 'icon' => 'bi-speedometer2', 'active' => 'admin.dashboard', 'can' => 'admin.access'],
            ]],
            ['label' => 'Design', 'items' => [
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
