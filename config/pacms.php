<?php

use App\Cms\Blocks\Types\AccordionBlock;
use App\Cms\Blocks\Types\AccordionItemBlock;
use App\Cms\Blocks\Types\AccountLinkBlock;
use App\Cms\Blocks\Types\ButtonBlock;
use App\Cms\Blocks\Types\ButtonGroupBlock;
use App\Cms\Blocks\Types\CardsBlock;
use App\Cms\Blocks\Types\ColumnBlock;
use App\Cms\Blocks\Types\ColumnsBlock;
use App\Cms\Blocks\Types\ContactInfoBlock;
use App\Cms\Blocks\Types\ContainerBlock;
use App\Cms\Blocks\Types\CopyrightBlock;
use App\Cms\Blocks\Types\CtaBlock;
use App\Cms\Blocks\Types\DividerBlock;
use App\Cms\Blocks\Types\DocumentBlock;
use App\Cms\Blocks\Types\EventsBlock;
use App\Cms\Blocks\Types\FaqBlock;
use App\Cms\Blocks\Types\GalleriesBlock;
use App\Cms\Blocks\Types\GalleryBlock;
use App\Cms\Blocks\Types\GlobalRefBlock;
use App\Cms\Blocks\Types\HeadingBlock;
use App\Cms\Blocks\Types\HeroBlock;
use App\Cms\Blocks\Types\HtmlBlock;
use App\Cms\Blocks\Types\IconBlock;
use App\Cms\Blocks\Types\ImageBlock;
use App\Cms\Blocks\Types\ListBlock;
use App\Cms\Blocks\Types\MediaCoverageBlock;
use App\Cms\Blocks\Types\MenuBlock;
use App\Cms\Blocks\Types\NewsBlock;
use App\Cms\Blocks\Types\PartnersBlock;
use App\Cms\Blocks\Types\ProgramsBlock;
use App\Cms\Blocks\Types\ProjectsBlock;
use App\Cms\Blocks\Types\PublicationsBlock;
use App\Cms\Blocks\Types\QuoteBlock;
use App\Cms\Blocks\Types\RepeatBlock;
use App\Cms\Blocks\Types\RichTextBlock;
use App\Cms\Blocks\Types\SectionBlock;
use App\Cms\Blocks\Types\SiteLogoBlock;
use App\Cms\Blocks\Types\SlideBlock;
use App\Cms\Blocks\Types\SliderBlock;
use App\Cms\Blocks\Types\SocialLinksBlock;
use App\Cms\Blocks\Types\SpacerBlock;
use App\Cms\Blocks\Types\StatisticsBlock;
use App\Cms\Blocks\Types\TeamBlock;
use App\Cms\Blocks\Types\TestimonialsBlock;
use App\Cms\Blocks\Types\VideoBlock;
use App\Cms\Blocks\Types\WhenBlock;
use App\Cms\Content\Types\EventType;
use App\Cms\Content\Types\GalleryType;
use App\Cms\Content\Types\MediaCoverageType;
use App\Cms\Content\Types\NewsType;
use App\Cms\Content\Types\PartnerType;
use App\Cms\Content\Types\ProgramType;
use App\Cms\Content\Types\ProjectType;
use App\Cms\Content\Types\PublicationType;
use App\Cms\Content\Types\TeamType;

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

    'version' => '0.9.0',

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

    'blocks' => [
        // Tree limits (SECURITY-ARCHITECTURE.md §4).
        'max_depth' => 12,
        'max_nodes' => 2000,
        'max_payload_kb' => 2048,
        // Core block types (CMS-ARCHITECTURE.md §5.5), in palette order.
        'types' => [
            SectionBlock::class,
            ContainerBlock::class,
            ColumnsBlock::class,
            ColumnBlock::class,
            HeadingBlock::class,
            RichTextBlock::class,
            ListBlock::class,
            ImageBlock::class,
            ButtonBlock::class,
            ButtonGroupBlock::class,
            IconBlock::class,
            VideoBlock::class,
            DividerBlock::class,
            SpacerBlock::class,
            HeroBlock::class,
            SliderBlock::class,
            SlideBlock::class,
            CardsBlock::class,
            StatisticsBlock::class,
            AccordionBlock::class,
            AccordionItemBlock::class,
            FaqBlock::class,
            QuoteBlock::class,
            CtaBlock::class,
            NewsBlock::class,
            EventsBlock::class,
            ProjectsBlock::class,
            ProgramsBlock::class,
            PublicationsBlock::class,
            TeamBlock::class,
            PartnersBlock::class,
            GalleriesBlock::class,
            GalleryBlock::class,
            TestimonialsBlock::class,
            MediaCoverageBlock::class,
            DocumentBlock::class,
            HtmlBlock::class,
            SiteLogoBlock::class,
            MenuBlock::class,
            SocialLinksBlock::class,
            ContactInfoBlock::class,
            CopyrightBlock::class,
            AccountLinkBlock::class,
            GlobalRefBlock::class,
            RepeatBlock::class,
            WhenBlock::class,
        ],
    ],

    // Testimonials submitted by registered users (CMS-ARCHITECTURE.md §18).
    'testimonials' => [
        // Stored with every submission; change the version whenever the consent text changes.
        'consent_version' => '1.0',
        'consent_text' => 'I agree that this testimonial, my name, organisation, designation and photo may be published on this website and edited for length or clarity.',
        // Users see why their testimonial was rejected (off by default: the reason is an internal note).
        'show_rejection_reason' => (bool) env('PACMS_TESTIMONIAL_SHOW_REJECTION_REASON', false),
        'body_max' => 1500,
        'photo_max_kb' => 2048,
        // Submitters' IP addresses are kept for abuse handling only, then removed.
        'ip_retention_days' => 90,
    ],

    'search' => [
        'per_page' => 10,
        'max_per_page' => 50,
    ],

    // Content modules with direct publishing (CMS-ARCHITECTURE.md §3.3). Each entry is a
    // ContentType; admin screens, workflow, archives, detail pages, blocks and sidebars
    // are generated from it.
    'content_types' => [
        NewsType::class,
        EventType::class,
        ProjectType::class,
        ProgramType::class,
        PublicationType::class,
        TeamType::class,
        PartnerType::class,
        GalleryType::class,
        MediaCoverageType::class,
    ],

    // Registered taxonomies. Content types add theirs as they are built.
    'taxonomies' => [
        'news_category' => ['label' => 'News categories', 'singular' => 'News category', 'hierarchical' => true],
        'event_category' => ['label' => 'Event categories', 'singular' => 'Event category', 'hierarchical' => true],
        'department' => ['label' => 'Departments', 'singular' => 'Department', 'hierarchical' => true],
        'partner_category' => ['label' => 'Partner categories', 'singular' => 'Partner category', 'hierarchical' => true],
        'publication_category' => ['label' => 'Publication categories', 'singular' => 'Publication category', 'hierarchical' => true],
        'media_coverage_category' => ['label' => 'Coverage categories', 'singular' => 'Coverage category', 'hierarchical' => true],
        'media_category' => ['label' => 'Media categories', 'singular' => 'Media category', 'hierarchical' => true],
        'tag' => ['label' => 'Tags', 'singular' => 'Tag', 'hierarchical' => false],
    ],

];
