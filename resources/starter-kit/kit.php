<?php

/*
 * The starter kit (php artisan pacms:starter). Files are in the JSON import format
 * (docs/CMS-BLOCK-SCHEMA.md); they are read through the import pipeline when installed.
 *
 * - globals: slug => definition. Installed first, because templates place them.
 * - templates: files in palette order. Each names its slug ("kit-…"), scope and category.
 * - pages: created as drafts with --pages, from a page template; never over an existing page.
 * - styles (Phase 11S): five design families. Headers, footers and the Home, About, Contact and
 *   Gallery templates come in each; "--style=bold" builds the pages and chooses the header and
 *   footer of that family. A template of a style is "<template>-<style>" (Classic: no suffix).
 */

return [
    'styles' => [
        'classic' => 'Classic',
        'bold' => 'Bold',
        'editorial' => 'Editorial',
        'impact' => 'Impact',
        'minimal' => 'Minimal',
        'institutional' => 'Institutional',
    ],

    // The header and footer of each style (Design → Header & footer shows them as cards).
    'style_globals' => [
        'header' => ['classic' => 'main-header', 'bold' => 'header-bold', 'editorial' => 'header-editorial', 'impact' => 'header-impact', 'minimal' => 'header-minimal', 'institutional' => 'header-institutional'],
        'footer' => ['classic' => 'main-footer', 'bold' => 'footer-bold', 'editorial' => 'footer-editorial', 'impact' => 'footer-impact', 'minimal' => 'footer-minimal', 'institutional' => 'footer-institutional'],
    ],

    // Menus "main" and "footer" are created (with the site's published top-level pages)
    // before these, because the header and footer show them.
    'globals' => [
        'main-header' => ['name' => 'Main header', 'kind' => 'header', 'file' => 'globals/main-header.json',
            'description' => 'Classic: logo, main menu, search and account link. Chosen in Design → Header & footer.'],
        'header-bold' => ['name' => 'Header: Bold', 'kind' => 'header', 'file' => 'globals/header-bold.json',
            'description' => 'Dark bar with a light logo, the menu, search and a Donate button.'],
        'header-editorial' => ['name' => 'Header: Editorial', 'kind' => 'header', 'file' => 'globals/header-editorial.json',
            'description' => 'Large centred logo with the menu on a thin line underneath.'],
        'header-impact' => ['name' => 'Header: Impact', 'kind' => 'header', 'file' => 'globals/header-impact.json',
            'description' => 'A coloured top bar with e-mail, phone and social links, then logo, menu and a Get involved button.'],
        'header-minimal' => ['name' => 'Header: Minimal', 'kind' => 'header', 'file' => 'globals/header-minimal.json',
            'description' => 'Logo, search and a Menu button that opens the menu as a drawer on every screen.'],
        'header-institutional' => ['name' => 'Header: Institutional', 'kind' => 'header', 'file' => 'globals/header-institutional.json',
            'description' => 'A primary-colour bar: logo, the menu in capitals and a Get involved button.'],
        'main-footer' => ['name' => 'Main footer', 'kind' => 'footer', 'file' => 'globals/main-footer.json',
            'description' => 'Classic: logo, a sentence about you, social links, footer menu, contact details and copyright.'],
        'footer-bold' => ['name' => 'Footer: Bold', 'kind' => 'footer', 'file' => 'globals/footer-bold.json',
            'description' => 'A big call to action band above a dark footer with menu and contact details.'],
        'footer-editorial' => ['name' => 'Footer: Editorial', 'kind' => 'footer', 'file' => 'globals/footer-editorial.json',
            'description' => 'Centred and quiet: logo, a sentence, social links and contact details.'],
        'footer-impact' => ['name' => 'Footer: Impact', 'kind' => 'footer', 'file' => 'globals/footer-impact.json',
            'description' => 'Dark, with a "Stay in touch" row above four columns.'],
        'footer-minimal' => ['name' => 'Footer: Minimal', 'kind' => 'footer', 'file' => 'globals/footer-minimal.json',
            'description' => 'One compact row: logo, copyright and social links.'],
        'footer-institutional' => ['name' => 'Footer: Institutional', 'kind' => 'footer', 'file' => 'globals/footer-institutional.json',
            'description' => 'A band of partner logos on white between primary-colour bands, then logo, menu, contact and social links.'],
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
        'templates/sections/institutional-photo-strip.json',
        'templates/sections/institutional-feature-row.json',
        'templates/sections/institutional-report.json',
        'templates/sections/institutional-news.json',
        'templates/sections/institutional-logo-band.json',
        'templates/pages/home.json',
        'templates/pages/home-bold.json',
        'templates/pages/home-editorial.json',
        'templates/pages/home-impact.json',
        'templates/pages/home-minimal.json',
        'templates/pages/home-programmes.json',
        'templates/pages/home-institutional.json',
        'templates/pages/about.json',
        'templates/pages/about-bold.json',
        'templates/pages/about-editorial.json',
        'templates/pages/about-impact.json',
        'templates/pages/about-minimal.json',
        'templates/pages/about-institutional.json',
        'templates/pages/contact.json',
        'templates/pages/contact-bold.json',
        'templates/pages/contact-editorial.json',
        'templates/pages/contact-impact.json',
        'templates/pages/contact-minimal.json',
        'templates/pages/contact-institutional.json',
        'templates/pages/gallery.json',
        'templates/pages/gallery-bold.json',
        'templates/pages/gallery-editorial.json',
        'templates/pages/gallery-impact.json',
        'templates/pages/gallery-minimal.json',
        'templates/pages/gallery-institutional.json',
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
