<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Probha Aurora CMS
    |--------------------------------------------------------------------------
    |
    | Application-level settings that belong to deployment/configuration.
    | Organisational content (site name, contact details, branding) lives in
    | the database `settings` table and is edited in the admin, never here.
    |
    */

    'version' => '0.2.0',

    'admin' => [
        'path' => 'admin',
    ],

    'security' => [
        // Roles that must have two-factor authentication confirmed before using the admin.
        'enforce_two_factor' => (bool) env('PACMS_ENFORCE_2FA', true),
        'two_factor_roles' => ['super-admin', 'administrator', 'editor'],

        // Minimum password length (SECURITY-ARCHITECTURE.md §2).
        'password_min_length' => 12,

        // Failed logins per email per hour before a temporary lockout.
        'login_attempts_per_hour' => 10,
    ],

    'registration' => [
        // Public self-registration creates "Registered User" accounts only.
        'enabled' => (bool) env('PACMS_PUBLIC_REGISTRATION', true),
        'default_role' => 'registered-user',
    ],

    'queue' => [
        // false: the scheduler starts a short-lived worker every minute (shared hosting friendly).
        'managed_worker' => (bool) env('PACMS_MANAGED_QUEUE_WORKER', false),
    ],

    'activity_log' => [
        'retention_days' => 365,
    ],

    'theme' => [
        // Generated design-token stylesheets are written here (on the "public" disk).
        'directory' => 'theme',
    ],

    'pages' => [
        'templates' => [
            'default' => 'Default',
            'full-width' => 'Full width',
        ],
        // First URL segments that belong to the application or content types and can
        // therefore not be used by a top-level page.
        'reserved_slugs' => [
            'admin', 'api', 'auth', 'build', 'storage', 'sanctum', 'account', 'preview', 'up',
            'sitemap', 'sitemaps', 'sitemap.xml', 'robots.txt', 'rss', 'rss.xml', 'search',
            'news', 'events', 'projects', 'programs', 'publications', 'media-coverage',
            'galleries', 'team', 'partners', 'testimonials', '__builder-preview',
        ],
        'max_depth' => 6,
    ],

    'revisions' => [
        // Non-published revisions kept per item (published revisions are always kept).
        'keep' => 50,
    ],

    'preview' => [
        'ttl_minutes' => 30,
    ],

    'media' => [
        'disk' => 'public',
        'private_disk' => 'local',
        // extension => [kind, allowed detected MIME types]. SVG is intentionally absent (D-10).
        'allowed' => [
            'jpg' => ['image', ['image/jpeg']],
            'jpeg' => ['image', ['image/jpeg']],
            'png' => ['image', ['image/png']],
            'gif' => ['image', ['image/gif']],
            'webp' => ['image', ['image/webp']],
            'avif' => ['image', ['image/avif']],
            'pdf' => ['document', ['application/pdf']],
            'docx' => ['document', ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip']],
            'xlsx' => ['document', ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip']],
            'pptx' => ['document', ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip']],
            'odt' => ['document', ['application/vnd.oasis.opendocument.text', 'application/zip']],
            'ods' => ['document', ['application/vnd.oasis.opendocument.spreadsheet', 'application/zip']],
            'odp' => ['document', ['application/vnd.oasis.opendocument.presentation', 'application/zip']],
            'txt' => ['document', ['text/plain']],
            'csv' => ['document', ['text/plain', 'text/csv', 'application/csv']],
            'mp4' => ['video', ['video/mp4']],
            'webm' => ['video', ['video/webm']],
            'mp3' => ['audio', ['audio/mpeg']],
            'm4a' => ['audio', ['audio/mp4', 'audio/x-m4a']],
        ],
        // Maximum upload size per kind, in kilobytes.
        'max_kb' => [
            'image' => 10 * 1024,
            'document' => 25 * 1024,
            'video' => 100 * 1024,
            'audio' => 20 * 1024,
        ],
        'max_megapixels' => 50,
        'max_dimension' => 12000,
        // Responsive WebP variants generated for images (never upscaled).
        'variant_widths' => [320, 640, 960, 1280, 1920],
        'quality' => 82,
    ],

    // Registered taxonomies. Content types add theirs as they are built.
    'taxonomies' => [
        'media_category' => ['label' => 'Media categories', 'singular' => 'Media category', 'hierarchical' => true],
        'tag' => ['label' => 'Tags', 'singular' => 'Tag', 'hierarchical' => false],
    ],

];
