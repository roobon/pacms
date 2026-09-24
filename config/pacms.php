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

];
