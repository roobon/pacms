<?php

namespace App\Auth;

/**
 * Single source of truth for PACMS permissions and the default role matrix
 * (SECURITY-ARCHITECTURE.md §3). Used by the seeder, admin role screens and tests.
 *
 * Code always checks permissions, never role names. The only exception is the
 * Super Admin, who is granted everything through Gate::before.
 */
final class PermissionCatalog
{
    public const SUPER_ADMIN = 'super-admin';

    public const REGISTERED_USER = 'registered-user';

    /** Content types that share the editorial permission set. */
    public const EDITORIAL_TYPES = [
        'pages', 'news', 'events', 'projects', 'programs', 'publications', 'media_coverage', 'galleries',
    ];

    public const EDITORIAL_ACTIONS = [
        'view', 'create', 'update_own', 'update_any', 'delete', 'submit', 'approve', 'publish',
    ];

    /**
     * Permissions grouped for display in the admin role editor.
     *
     * @return array<string, list<string>>
     */
    public static function groups(): array
    {
        $groups = [
            'Admin' => ['admin.access', 'dashboard.view'],
        ];

        foreach (self::EDITORIAL_TYPES as $type) {
            $groups[self::label($type)] = array_map(
                fn (string $action) => "{$type}.{$action}",
                self::EDITORIAL_ACTIONS,
            );
        }

        $groups['Pages'][] = 'pages.custom_css';

        return $groups + [
            'Organization' => ['team.manage', 'partners.manage'],
            'Testimonials' => ['testimonials.view', 'testimonials.moderate', 'testimonials.publish', 'testimonials.delete'],
            'Media' => ['media.view', 'media.upload', 'media.update', 'media.delete', 'media.force_delete', 'media.upload_svg'],
            'Blocks & Design' => [
                'blocks.custom_css', 'blocks.custom_attributes', 'block_types.manage', 'templates.manage',
                'global_blocks.manage', 'global_blocks.detach', 'menus.manage', 'design_tokens.manage',
            ],
            'Integrations' => ['external_sources.manage', 'external_sources.sync', 'facebook.connect'],
            'SEO' => ['seo.manage', 'redirects.manage'],
            'Import / Export' => ['import.run', 'export.run'],
            'Users' => ['users.view', 'users.manage', 'users.manage_roles'],
            'System' => ['settings.manage', 'activity_log.view', 'backups.manage', 'revisions.restore'],
        ];
    }

    /** @return list<string> */
    public static function all(): array
    {
        return array_values(array_merge(...array_values(self::groups())));
    }

    /**
     * Default role → permissions matrix (proposal D-07; editable in the admin afterwards).
     *
     * @return array<string, array{label: string, permissions: list<string>}>
     */
    public static function roles(): array
    {
        $editorial = fn (array $actions) => self::expand(self::EDITORIAL_TYPES, $actions);

        $contributor = array_merge(
            ['admin.access', 'dashboard.view', 'media.view', 'media.upload'],
            $editorial(['view', 'create', 'update_own', 'submit']),
        );

        $author = array_merge($contributor, ['export.run']);

        $editor = array_merge(
            ['admin.access', 'dashboard.view'],
            $editorial(self::EDITORIAL_ACTIONS),
            [
                'team.manage', 'partners.manage',
                'testimonials.view', 'testimonials.moderate', 'testimonials.publish', 'testimonials.delete',
                'media.view', 'media.upload', 'media.update', 'media.delete',
                'templates.manage', 'global_blocks.manage', 'global_blocks.detach', 'menus.manage',
                'external_sources.sync', 'seo.manage', 'redirects.manage',
                'import.run', 'export.run', 'revisions.restore',
            ],
        );

        $administrator = array_values(array_diff(self::all(), ['users.manage_roles']));

        return [
            self::SUPER_ADMIN => ['label' => 'Super Admin', 'permissions' => self::all()],
            'administrator' => ['label' => 'Administrator', 'permissions' => $administrator],
            'editor' => ['label' => 'Editor', 'permissions' => $editor],
            'author' => ['label' => 'Author', 'permissions' => $author],
            'contributor' => ['label' => 'Contributor', 'permissions' => $contributor],
            'moderator' => ['label' => 'Moderator', 'permissions' => [
                'admin.access', 'dashboard.view', 'testimonials.view', 'testimonials.moderate', 'testimonials.publish',
            ]],
            self::REGISTERED_USER => ['label' => 'Registered User', 'permissions' => []],
        ];
    }

    public static function roleLabel(string $role): string
    {
        return self::roles()[$role]['label'] ?? ucwords(str_replace('-', ' ', $role));
    }

    /**
     * @param  list<string>  $types
     * @param  list<string>  $actions
     * @return list<string>
     */
    private static function expand(array $types, array $actions): array
    {
        $permissions = [];
        foreach ($types as $type) {
            foreach ($actions as $action) {
                $permissions[] = "{$type}.{$action}";
            }
        }

        return $permissions;
    }

    private static function label(string $type): string
    {
        return ucwords(str_replace('_', ' ', $type));
    }
}
