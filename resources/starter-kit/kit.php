<?php

/*
 * The starter kit (php artisan pacms:starter). Files are in the JSON import format
 * (docs/CMS-BLOCK-SCHEMA.md); they are read through the import pipeline when installed.
 *
 * - globals: slug => definition. Installed first, because templates place them.
 * - templates: files in palette order. Each names its slug ("kit-…"), scope and category.
 * - pages: created as drafts with --pages, from a page template; never over an existing page.
 */

return [
    'globals' => [
        'call-to-action' => ['name' => 'Call to action', 'kind' => 'generic', 'file' => 'globals/call-to-action.json',
            'description' => 'The band at the bottom of the starter pages. Edit it once and it changes everywhere.'],
        'contact-details' => ['name' => 'Contact details', 'kind' => 'generic', 'file' => 'globals/contact-details.json',
            'description' => 'E-mail, phone and address, for pages and sidebars.'],
        'sidebar-news' => ['name' => 'Sidebar: news', 'kind' => 'sidebar', 'file' => 'globals/sidebar-news.json',
            'description' => 'Beside news items: an invitation and upcoming events.'],
        'sidebar-events' => ['name' => 'Sidebar: events', 'kind' => 'sidebar', 'file' => 'globals/sidebar-events.json',
            'description' => 'Beside events: how to join, and the latest news.'],
        'sidebar-general' => ['name' => 'Sidebar: general', 'kind' => 'sidebar', 'file' => 'globals/sidebar-general.json',
            'description' => 'For any module: get involved and contact.'],
    ],

    'templates' => [
        'templates/sections/hero-centred.json',
        'templates/sections/hero-split.json',
        'templates/sections/hero-slider.json',
        'templates/sections/intro-image.json',
        'templates/sections/impact-numbers.json',
        'templates/sections/feature-cards.json',
        'templates/sections/latest-news.json',
        'templates/sections/upcoming-events.json',
        'templates/sections/testimonials.json',
        'templates/sections/partner-logos.json',
        'templates/sections/team-grid.json',
        'templates/sections/faq.json',
        'templates/sections/contact-details.json',
        'templates/sections/cta-band.json',
        'templates/pages/home.json',
        'templates/pages/about.json',
        'templates/pages/contact.json',
        'templates/pages/get-involved.json',
        'templates/pages/news.json',
        'templates/pages/events.json',
        'templates/pages/projects.json',
        'templates/pages/programs.json',
        'templates/pages/publications.json',
    ],

    'pages' => [
        ['title' => 'Home', 'slug' => 'home', 'template' => 'kit-page-home', 'home' => true,
            'excerpt' => 'Your mission in one clear sentence.'],
        ['title' => 'About us', 'slug' => 'about', 'template' => 'kit-page-about', 'layout' => 'full-width',
            'excerpt' => 'Who we are and how we work.'],
        ['title' => 'Contact', 'slug' => 'contact', 'template' => 'kit-page-contact', 'layout' => 'full-width',
            'excerpt' => 'How to reach us.'],
        ['title' => 'Get involved', 'slug' => 'get-involved', 'template' => 'kit-page-get-involved', 'layout' => 'full-width',
            'excerpt' => 'Volunteer, bring a programme to your school, or partner with us.'],
    ],
];
