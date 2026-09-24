# Probha Aurora CMS — Final Master Project Prompt (reference copy)

Source: the team's final master prompt (ChatGPT shared conversation, retrieved 2026-09-24). Kept **verbatim** as the reference specification. Where the Phase 1 architecture deliberately deviates from it, the deviation is listed in [PHASE-1-ARCHITECTURE-REVIEW.md §4–5](PHASE-1-ARCHITECTURE-REVIEW.md).

```text
============================================================
PROBHA AURORA CMS
FINAL MASTER PROJECT PROMPT
============================================================

PROJECT NAME
Probha Aurora CMS

Suggested Repository:
probha-aurora-cms

Documentation Title:
Probha Aurora CMS — Architecture & Technical Specification

Admin Product Name:
Probha Aurora CMS

Internal Short Name:
PACMS


============================================================
1. PROJECT OVERVIEW
============================================================

Build a secure, maintainable, extensible, high-performance
Organizational Content Management System named:

                    PROBHA AURORA CMS

This is NOT an inventory management system.

The CMS will serve as the central organizational content hub
for managing and publishing:

- Pages
- News
- Events
- Projects
- Programs
- Publications
- Team
- Partners
- Testimonials
- Media Coverage
- Galleries
- Menus
- Global Blocks
- Reusable Templates
- Media
- RSS feeds
- External content
- SEO metadata
- Structured content
- Dynamic content
- Custom visual blocks
- AI-generated/imported CMS structures

The system must be designed to evolve for many years without
requiring major architectural rewrites.

The goal is NOT maximum code volume.

The goal is:

- Maintainability
- Security
- Extensibility
- Performance
- Reusability
- Accessibility
- Clean architecture
- Strong separation of concerns
- Excellent administrator experience
- Excellent public visitor experience


============================================================
2. DEVELOPMENT TEAM
============================================================

TEAM LEADER / LEAD DEVELOPER

Syed Ziaul Habib


TEAM MEMBERS

Hasibul Hasan

Khandoker Humayoun Kobir


============================================================
3. AI-ASSISTED DEVELOPMENT SUPPORT
============================================================

The development process is supported by:

1. ChatGPT
2. Claude Code

AI-assisted development may be used for:

- Architecture planning
- System design
- Database design
- API design
- Code generation
- Code review
- Refactoring
- Debugging
- Testing guidance
- Security analysis
- Performance analysis
- Documentation
- UI/UX guidance
- Development planning
- Technical research

AI assistance does NOT replace developer responsibility.

All AI-generated or AI-assisted implementation must be:

- Reviewed
- Tested
- Security validated
- Architecturally reviewed
- Approved by the development team

Do not blindly accept AI-generated code.

Human developers remain responsible for:

- Architecture decisions
- Security
- Data protection
- Code quality
- Production deployment
- Business logic
- Testing
- Final approval


============================================================
4. TECHNOLOGY STACK
============================================================

BACKEND

- Laravel 12
- PHP 8.3+
- MySQL 8+
- Laravel REST API
- Laravel Sanctum
- Spatie Laravel Permission
- Laravel Policies / Gates
- Laravel Queue
- Laravel Scheduler
- Laravel Cache
- Laravel Storage


PUBLIC FRONTEND

- React
- Vite
- React Router
- Axios
- Bootstrap 5
- Bootstrap Icons


ADMIN / CMS

- Laravel server-rendered UI
- Blade or equivalent Laravel server-side UI
- Bootstrap 5
- Bootstrap Icons
- React only where complex interaction genuinely requires it


DEVELOPMENT

- Git
- Composer
- NPM
- Vite
- PHPUnit / Pest as appropriate
- Laravel testing tools


IMPORTANT:

Do NOT make the entire Admin system a React SPA.

Do NOT make React mandatory for every admin screen.

Use server-rendered Laravel UI for normal CRUD and administrative
operations.

Use React selectively for complex interactive features such as:

- Visual Block Builder
- Drag/drop
- Nested blocks
- Live preview
- Media picker
- Repeater editor
- Responsive controls
- Interactive style controls
- AI JSON import/export
- Other genuinely interactive interfaces


============================================================
5. HIGH-LEVEL ARCHITECTURE
============================================================

                    PROBHA AURORA CMS
                           |
                    Laravel 12 Backend
                           |
              +------------+------------+
              |                         |
        ADMIN / CMS UI             PUBLIC WEBSITE
              |                         |
      Laravel Server UI            React SPA
      + React where needed         REST API
              |                         |
              +------------+------------+
                           |
                         MySQL


Laravel Backend is the authoritative source for:

- Data
- Authentication
- Authorization
- Business logic
- Content
- Publishing
- Media
- Block definitions
- External providers
- Security
- API


PUBLIC WEBSITE

The public website should be a React SPA consuming the Laravel
REST API.

Focus on:

- Fast navigation
- API caching
- Code splitting
- Lazy loading
- Optimized images
- Responsive images
- Prefetching where useful
- Minimal initial bundle
- Skeleton/loading states
- Error states
- SEO metadata
- Canonical URLs
- Open Graph
- Structured data
- Accessibility


Do not automatically introduce SSR or SSG unless architecture
review determines it is necessary.

The preferred public architecture is a performance-focused
React SPA that remains SEO-ready.


============================================================
6. UI / UX DESIGN SYSTEM
============================================================

Use:

- Bootstrap 5
- Bootstrap Icons

Do NOT use Font Awesome by default.

The interface must NOT look like unmodified/default Bootstrap.

Build a custom design system on top of Bootstrap.

Design tokens should include:

- Primary
- Secondary
- Accent
- Heading
- Body
- Muted
- Light Background
- Dark Background
- Border
- Success
- Warning
- Danger

Also define:

- Typography scale
- Spacing scale
- Container width
- Border radius
- Shadows
- Buttons
- Forms
- Cards
- Tables
- Navigation
- Badges
- Alerts
- Modals
- Empty states
- Loading states
- Error states
- Transitions

Design characteristics:

- Modern
- Professional
- Clean
- Spacious
- Accessible
- Responsive
- Fast
- Consistent

Use subtle micro-interactions.

Avoid excessive animation.

Accessibility must be built in:

- Semantic HTML
- Keyboard navigation
- Visible focus states
- Appropriate ARIA
- Screen-reader labels
- Alt text
- Accessible forms
- Accessible menus
- Accessible dialogs
- Proper color contrast


============================================================
7. CORE CMS PRINCIPLE
============================================================

Separate:

CONTENT

from

PRESENTATION.

The CMS must distinguish:

1. Content Source
2. Display Mode
3. Layout
4. Style
5. Responsive behavior
6. Design Tokens


CONTENT SOURCE:

Where does the content come from?

- Static
- Dynamic
- External


DISPLAY MODE:

How should content be presented?

Examples:

- Grid
- Masonry
- Carousel
- Slider
- List
- Cards
- Featured
- Quote Slider
- Justified
- Thumbnail + Large


LAYOUT:

Where/how is it positioned?

- Width
- Height
- Columns
- Alignment
- Gap
- Padding
- Margin
- Container
- Position


STYLE:

How does it look?

- Background
- Typography
- Border
- Radius
- Shadow
- Overlay
- Animation


RESPONSIVE:

How does it behave at:

- Desktop
- Tablet
- Mobile


DESIGN TOKENS:

Global organizational visual identity.


============================================================
8. NESTED VISUAL BLOCK ENGINE
============================================================

The Block Engine is the heart of the CMS.

It MUST support nested blocks.

Example:

Hero Section
|
+-- Background Image
+-- Overlay
+-- Content Container
    |
    +-- Heading
    +-- Rich Text
    +-- Button


Example:

Accordion Section
|
+-- Heading
+-- Description
+-- Accordion
    |
    +-- Accordion Item
        +-- Question
        +-- Answer
    |
    +-- Accordion Item
    |
    +-- Accordion Item


Example:

Slider
|
+-- Slide
|   +-- Image
|   +-- Heading
|   +-- Description
|   +-- Button
|
+-- Slide
|
+-- Slide


The renderer must be recursive:

BlockRenderer
    ->
Block Type
    ->
Block Component
    ->
Children
    ->
BlockRenderer


Do NOT create a flat block architecture.

Nested blocks must be represented properly in the database,
using parent/child relationships or another well-designed
hierarchical structure.

Do not flatten everything into one giant JSON field if that
would harm:

- Editing
- Querying
- Authorization
- Revisions
- Performance
- Maintainability


============================================================
9. STANDARD BLOCK CATEGORIES
============================================================

BASIC BLOCKS

- Heading
- Rich Text
- Image
- Video
- Button
- Divider
- Spacer
- Icon


LAYOUT BLOCKS

- Container
- Full Width Section
- One Column
- Two Columns
- Three Columns
- Four Columns
- Grid
- Flex Row
- Image + Text
- Text + Image


VISUAL / CONTENT BLOCKS

- Hero
- Banner
- Slider
- Carousel
- Cards
- Statistics
- Timeline
- Accordion
- FAQ
- Tabs
- Quote
- CTA


MEDIA BLOCKS

- Gallery
- Video Gallery
- Document List


ORGANIZATIONAL BLOCKS

- Team
- Partners
- Programs
- Projects
- Publications
- Testimonials
- Media Coverage


DYNAMIC BLOCKS

- Latest News
- Featured News
- News by Category
- Upcoming Events
- Projects
- Programs
- Publications
- Team
- Partners
- Testimonials
- Media Coverage
- Galleries


EXTERNAL BLOCKS

- RSS Feed
- Facebook Feed

Future:

- YouTube
- Instagram
- LinkedIn
- Other providers


============================================================
10. STATIC / DYNAMIC / EXTERNAL CONTENT
============================================================

Each appropriate block may support:

[ STATIC ] [ DYNAMIC ] [ EXTERNAL ]


STATIC:

Content manually entered by administrator.


DYNAMIC:

Content comes from CMS database entities.


EXTERNAL:

Content comes from an external provider.


Not every block needs to support all three modes.

The architecture must allow capability configuration per block.


============================================================
11. DISPLAY MODES
============================================================

Display mode is a presentation property.

Do NOT create separate block types such as:

NewsGridBlock
NewsCarouselBlock
NewsMasonryBlock

Instead use:

NewsBlock

with:

display.mode


Example:

{
  "type": "news",
  "source": {
    "mode": "dynamic",
    "provider": "cms",
    "entity": "news",
    "limit": 6,
    "order": "latest"
  },
  "display": {
    "mode": "carousel",
    "columns": {
      "desktop": 3,
      "tablet": 2,
      "mobile": 1
    }
  }
}


============================================================
12. BLOCK EDITOR
============================================================

Each block should logically have:

CONTENT

LAYOUT

STYLE

ADVANCED


CONTENT:

Actual content/data.


LAYOUT:

- Container
- Width
- Columns
- Alignment
- Gap
- Padding
- Margin
- Height
- Position


STYLE:

- Background
- Typography
- Border
- Radius
- Shadow
- Overlay
- Animation


ADVANCED:

- CSS class
- Custom attributes
- Custom CSS where authorized
- Visibility
- Other advanced settings


Responsive settings:

Desktop
Tablet
Mobile


Use inheritance/default values to avoid unnecessary repetitive
configuration.


============================================================
13. DESIGN TOKEN SYSTEM
============================================================

Global tokens must include:

- Colors
- Typography
- Spacing
- Container widths
- Radius
- Shadows
- Button styles
- Form styles

Blocks should reference tokens wherever appropriate.

The organization should be able to change branding through the
design system without rewriting block components.


============================================================
14. CUSTOM BLOCK BUILDER
============================================================

Administrators must be able to create custom blocks without
changing PHP source code.

Custom block properties:

- Name
- Slug
- Description
- Icon
- Category
- Status
- Fields
- Default settings
- Layout
- Style


FIELD TYPES:

- Text
- Textarea
- Rich Text
- Number
- URL
- Email
- Image
- Video
- File
- Icon
- Color
- Date
- Time
- Date & Time
- Checkbox
- Radio
- Select
- Multi-select
- Gallery
- Relationship
- Repeater


REPEATERS ARE ESSENTIAL.

Support repeaters for:

- Slider
- Accordion
- Cards
- Statistics
- FAQs
- Timeline
- Programs
- Projects
- Team
- Partners
- Testimonials
- Publications

Repeater functionality:

- Add
- Remove
- Duplicate
- Reorder
- Validation
- Nested fields where appropriate


============================================================
15. GLOBAL BLOCKS
============================================================

Support global blocks such as:

- Header
- Footer
- Contact Information
- Partner Logos
- Newsletter
- Social Links
- Organization Statistics
- CTA

Global blocks update everywhere.

Authorized users may detach/unlink a global block where
appropriate.


============================================================
16. REUSABLE TEMPLATES
============================================================

Support reusable templates such as:

- Hero Default
- Hero Background Image
- Program Cards
- Project Cards
- News Cards
- Statistics
- Partner Logos
- Staff Profile
- CTA Banner
- Accordion
- Slider
- Testimonials

Templates should be reusable and versionable where appropriate.


============================================================
17. HEADER / FOOTER
============================================================

HEADER:

Header
|
+-- Top Bar
+-- Logo
+-- Main Navigation
+-- Social Links
+-- CTA


FOOTER:

Footer
|
+-- Logo
+-- About
+-- Footer Menu
+-- Contact
+-- Social
+-- Newsletter
+-- Copyright


Header and Footer are Global Blocks.


============================================================
18. MENU BUILDER
============================================================

Support:

- Main Menu
- Footer Menu
- Secondary Menu
- Custom Menu


Menu item types:

- Page
- News
- Event
- Project
- Program
- Publication
- Category
- External URL
- Custom URL
- Menu Group


Features:

- Nested hierarchy
- Drag/drop
- Parent
- Order
- Visibility
- New tab
- Icon
- CSS class


Support:

- Standard dropdown
- Multi-level dropdown
- Mega menu
- Mobile offcanvas
- Mobile accordion


Mega menu may contain:

- Columns
- Headings
- Menu groups
- Images
- Text
- Buttons


============================================================
19. CONTENT MODULES
============================================================

FIRST-CLASS CONTENT TYPES:

- News
- Events
- Projects
- Programs
- Publications
- Media Coverage
- Gallery
- Team
- Partners
- Testimonials


------------------------------------------------------------
NEWS
------------------------------------------------------------

Fields:

- Title
- Slug
- Excerpt
- Content blocks
- Featured image
- Category
- Tags
- Author
- Published date
- Status
- SEO


------------------------------------------------------------
EVENTS
------------------------------------------------------------

Fields:

- Title
- Slug
- Description
- Image
- Start date/time
- End date/time
- Venue
- Address
- Registration URL
- Organizer
- Status


------------------------------------------------------------
PROJECTS
------------------------------------------------------------

Fields:

- Name
- Slug
- Description
- Featured image
- Status
- Dates
- Location
- Partners
- Manager
- Gallery
- Documents
- Website URL


------------------------------------------------------------
PROGRAMS
------------------------------------------------------------

Fields:

- Name
- Slug
- Description
- Image
- Objectives
- Activities
- Partners
- Documents
- Gallery
- Status


------------------------------------------------------------
PUBLICATIONS
------------------------------------------------------------

Fields:

- Title
- Description
- Cover
- Date
- Author
- Category
- PDF/document
- External URL
- Featured
- Status


------------------------------------------------------------
TEAM
------------------------------------------------------------

Fields:

- Name
- Designation
- Department
- Photo
- Biography
- Email where appropriate
- Social links
- Display order
- Status


------------------------------------------------------------
PARTNERS
------------------------------------------------------------

Fields:

- Organization
- Logo
- Description
- Website
- Category
- Display order
- Status


============================================================
20. TESTIMONIALS / MODERATION
============================================================

Testimonials are moderated user-generated content.

Workflow:

Registered User
    |
Submit Testimonial
    |
Pending
    |
Under Review
    |
+---------+---------+
|                   |
Approve             Reject
|                   |
Published           Rejected


Statuses:

- Draft
- Pending
- Under Review
- Approved
- Published
- Rejected
- Archived


Fields:

- Name
- Designation
- Organization
- Photo
- Testimonial Text
- Rating optional
- Quote/citation
- Date
- Related Program
- Related Project
- Related Event
- Website/Social URL
- Status
- user_id internally


Submission form:

- Name
- Organization
- Designation
- Photo
- Testimonial
- Related Program
- Related Project
- Consent checkbox


Internally link user_id.

Never publicly expose:

- Email
- User ID
- IP
- Private account information


Permissions:

- testimonials.view
- testimonials.moderate
- testimonials.publish
- testimonials.delete


Moderators can:

- Preview
- Edit
- Approve
- Reject
- Publish
- Archive
- Delete


Keep an internal rejection reason.


Display modes:

- Single
- Cards
- Grid
- Carousel
- Quote Slider
- Featured
- List
- Masonry


============================================================
21. MEDIA COVERAGE
============================================================

Media Coverage is separate from News.

It is an organizational media archive.

Fields:

- Title
- Category
- Tags
- Source Name
- Original Source URL
- Publication Date
- Coverage Type
- Description/Summary
- Featured Image
- Archived PDF
- Archived Video
- Related Program
- Related Project
- Featured
- Status
- Source availability
- Last Checked
- HTTP Status


Coverage Types:

- Newspaper
- Magazine
- TV
- Radio
- Online
- Other


Categories are relational/admin-managed.

Examples only:

- Environment
- Eco-Schools
- Newspaper
- Television
- Online Media
- Magazine
- Radio
- Interview
- Documentary
- Event Coverage


Keep Category and Coverage Type separate.


ARCHIVE FALLBACK:

MEDIA COVERAGE
|
+-- Original Source URL
|
+-- Archived Content
    +-- PDF
    +-- Video


Behavior:

Original working:
    Show original

Original unavailable + PDF:
    Show PDF

Original unavailable + Video:
    Show video

Both available:
    Show original + archive


Use scheduled Laravel source checks.

Do NOT perform source checks on every visitor request.

Track:

- Last checked
- HTTP status
- Availability
- Error


Provide admin action:

Check Source


Legal requirement:

Do not archive or redistribute third-party content without
appropriate permission, license, or legal basis.


============================================================
22. GALLERY SYSTEM
============================================================

Gallery is a first-class media collection.

Sources:

STATIC:
CMS Media Library

DYNAMIC:
CMS Gallery Items

EXTERNAL:
- Facebook
- Flickr
- RSS / Media RSS
- YouTube
- Future providers


Gallery fields:

- Title
- Slug
- Description
- Cover Image
- Gallery Type
- Gallery Items
- Caption
- Alt Text
- Credit
- Event
- Project
- Program
- Date
- Location
- Photographer/Credit
- Tags
- Featured
- Status


Gallery types:

- Photo
- Video
- Mixed


PROVIDER ARCHITECTURE:

Gallery Provider Interface
        |
  +-----+------+---------+
  |            |         |
 CMS        Facebook   Flickr
  |            |         |
  +------------+---------+
               |
       Gallery Normalizer
               |
         Gallery Block


Normalized item:

{
  "id": "external-123",
  "title": "Eco Schools Activity",
  "type": "image",
  "url": "...",
  "thumbnail_url": "...",
  "caption": "...",
  "source_url": "...",
  "published_at": "2026-09-20"
}


Display:

- Grid
- Masonry
- Justified
- Carousel
- Slider
- Thumbnail + Large
- Lightbox
- 2 columns
- 3 columns
- 4 columns
- Responsive


The renderer must not care which provider supplied the content.


============================================================
23. EXTERNAL PROVIDER ARCHITECTURE
============================================================

Create:

ExternalContentProviderInterface


Providers:

- RSSProvider
- FacebookProvider

Future:

- YouTubeProvider
- InstagramProvider
- LinkedInProvider
- Other providers


Server-side provider logic only.

Normalize external data where appropriate.

Example:

{
  "id": "...",
  "source_id": "...",
  "title": "...",
  "link": "...",
  "guid": "...",
  "description": "...",
  "excerpt": "...",
  "author": "...",
  "category": "...",
  "image_url": "...",
  "published_at": "...",
  "updated_at": "...",
  "raw_data": {}
}


============================================================
24. RSS SYSTEM
============================================================

The CMS must support BOTH directions.

A.

CMS -> RSS -> Other Websites


B.

Other Websites -> RSS -> CMS -> RSS Block -> Public Website


------------------------------------------------------------
RSS DISTRIBUTION
------------------------------------------------------------

Provide:

/rss.xml
/rss/news.xml
/rss/events.xml
/rss/projects.xml
/rss/programs.xml
/rss/publications.xml


Only published content.

RSS must include appropriate:

- Title
- Description
- Site URL
- Feed URL
- Language
- Last build date
- GUID
- Publication date
- Category
- Author where appropriate
- Organization image where appropriate


Support filtered feeds where appropriate.

Do not expose arbitrary database queries.


------------------------------------------------------------
RSS SOURCES
------------------------------------------------------------

Admin fields:

- Name
- Feed URL
- Source website
- Description
- Status
- Cache duration
- Max items
- Last sync
- Last status
- Last error


Actions:

- Add
- Edit
- Delete
- Enable
- Disable
- Test
- Sync Now
- Clear Cache


------------------------------------------------------------
RSS BLOCK
------------------------------------------------------------

Configuration:

- Source
- Item count
- Layout
- Columns
- Show image
- Show title
- Show excerpt
- Show date
- Show author
- Show source
- Show category
- Read more
- Open in new tab
- Cache duration


Layouts:

- List
- Grid
- Cards
- 2-column
- 3-column
- Carousel


------------------------------------------------------------
RSS SECURITY
------------------------------------------------------------

React must NEVER directly fetch arbitrary RSS URLs.

Use:

External RSS
    ->
Laravel RSS Service
    ->
Validate
    ->
Fetch
    ->
Safe Parse
    ->
Sanitize
    ->
Normalize
    ->
Cache
    ->
Laravel API
    ->
React


Protect against:

- SSRF
- localhost
- private network IPs
- internal IP ranges
- unsafe redirects
- file://
- arbitrary protocols
- XML entity attacks
- XML bombs
- oversized responses
- excessive timeouts
- malicious HTML
- code execution


Use:

- HTTPS preference
- Timeout
- Maximum response size
- Redirect validation
- Protocol validation
- Safe XML parser
- HTML sanitization


Cache durations:

- 5m
- 15m
- 30m
- 1h
- 6h
- 12h
- 24h


On failure:

- Use cached data if available
- Show graceful fallback
- Log failure
- Update admin status


============================================================
25. FACEBOOK / META
============================================================

NEVER scrape Facebook.

Use official Meta/Facebook APIs where supported.

Before implementation, verify the CURRENT official Meta
requirements, including:

- Available endpoints
- Permissions
- Authentication
- App requirements
- Policies
- Available content
- Rate limits

Do not rely on outdated assumptions.

Credentials must remain server-side.

Provide:

- Sync Now
- Last success
- Last error
- Status
- Scheduled synchronization


Use:

- Laravel Queue
- Laravel Scheduler
- Caching


On failure:

- Use cached content
- Graceful fallback
- Log error
- Show provider status to administrator


============================================================
26. HOMEPAGE ARCHITECTURE
============================================================

The homepage must NOT be hard-coded.

Example:

Header Global Block
    |
Hero
    |
Introduction
    |
Statistics Dynamic
    |
Programs Dynamic
    |
Projects Dynamic
    |
Latest News Dynamic
    |
Events Dynamic
    |
Testimonials Dynamic
    |
RSS External
    |
Facebook External
    |
Partners Dynamic
    |
CTA
    |
Footer Global Block


The homepage should itself be assembled from CMS blocks.


============================================================
27. PAGE MANAGEMENT
============================================================

Page fields:

- Title
- Slug
- Excerpt
- Featured image
- Nested blocks
- Status
- Author
- Dates
- SEO
- Custom CSS where authorized


Workflow:

Draft
 ->
Review
 ->
Approved
 ->
Published
 ->
Archived


Actions:

- Save Draft
- Preview
- Submit for Review
- Approve
- Publish
- Unpublish
- Archive


Secure preview must be supported.

Unpublished content must NEVER be publicly accessible.


============================================================
28. REVISIONS
============================================================

Support revisions for content and blocks.

Store:

- Previous version
- Current version
- Changed by
- Date/time
- Change information where appropriate


Authorized users can:

- View
- Compare
- Restore


============================================================
29. MEDIA LIBRARY
============================================================

Support:

- Images
- Videos
- PDFs
- Documents


Features:

- Upload
- Multiple upload
- Drag/drop
- Preview
- Search
- Filter
- Sort
- Categories
- Tags
- Alt text
- Caption
- Description
- File size
- Dimensions
- MIME type
- Upload date
- Uploader
- Delete
- Replace


Security:

- MIME validation
- Extension validation
- Secure filenames
- Storage isolation
- Upload limits
- Authorization
- No executable uploads


============================================================
30. SEO
============================================================

Support:

- SEO title
- Meta description
- Canonical URL
- Robots
- Open Graph title
- Open Graph description
- Open Graph image
- Twitter/X card
- Structured data
- XML sitemap
- robots.txt
- Clean URLs
- Redirects


============================================================
31. SEARCH
============================================================

Search:

- Pages
- News
- Events
- Projects
- Programs
- Publications

Support:

- Keyword search
- Content type
- Filters
- Pagination


Architecture should allow future content types.


============================================================
32. AI-COMPATIBLE JSON SYSTEM
============================================================

AI tools such as:

- ChatGPT
- Claude
- Gemini

may generate CMS JSON.

AI must generate CMS configuration/content JSON.

AI must NOT generate executable:

- PHP
- JavaScript
- SQL
- Server commands
- Arbitrary code


The CMS validates and renders the JSON.

AI-generated JSON must NEVER be executed as code.


------------------------------------------------------------
VERSIONED JSON SCHEMA
------------------------------------------------------------

Example:

{
  "schema_version": "1.0",
  "type": "section",
  "name": "Programs Showcase",
  "slug": "programs-showcase",
  "source": {
    "mode": "dynamic",
    "provider": "cms",
    "entity": "programs"
  },
  "content": {},
  "children": [],
  "display": {
    "mode": "grid"
  },
  "layout": {},
  "style": {},
  "responsive": {},
  "advanced": {}
}


Create:

CMS-BLOCK-SCHEMA.md


Document:

- Schema version
- Block types
- Fields
- Repeaters
- Children
- Sources
- Entities
- Relationships
- Display modes
- Layout
- Style
- Responsive
- Design tokens
- Validation
- Import
- Export
- Assets
- Versioning
- Examples


============================================================
33. JSON IMPORT
============================================================

Import pipeline:

JSON
 ->
Schema Validation
 ->
Security Validation
 ->
Reference Validation
 ->
Preview
 ->
User Confirmation
 ->
Import
 ->
Report


The import report must identify:

- Pages
- Blocks
- Static content
- Dynamic content
- Missing assets
- Missing references
- Unsupported properties
- External configuration requirements


Asset strategies:

1. Keep external URL
2. Download into Media Library
3. Replace existing media


Missing internal media IDs must NEVER be silently substituted.

Report them clearly.


============================================================
34. JSON EXPORT
============================================================

Support exporting:

- Individual block
- Section
- Template
- Complete page

Future:

- Complete site package


============================================================
35. DATABASE ARCHITECTURE
============================================================

Candidate tables:

- users
- roles
- permissions
- model_has_roles
- model_has_permissions
- pages
- page_blocks
- block_types
- block_fields
- block_templates
- global_blocks
- block_revisions
- news
- news_categories
- news_tags
- events
- projects
- programs
- publications
- teams
- partners
- testimonials
- testimonial_moderation
- media
- media_categories
- media_tags
- menus
- menu_items
- rss_sources
- rss_items
- rss_feeds
- external_provider_accounts
- external_sync_logs
- social_accounts
- social_posts
- seo_metadata
- activity_logs
- revisions
- settings
- media_coverage
- media_coverage_categories
- media_coverage_tags
- galleries
- gallery_items
- gallery_sources


Review and refine this list during Phase 1.

Use relational tables for structured entities.

Use JSON only where genuinely appropriate, such as:

- Flexible block configuration
- Custom block definitions
- Flexible style/layout configuration where justified


Use:

- Proper foreign keys
- Indexes
- Unique constraints
- Appropriate cascading/restrict behavior
- Soft deletes where appropriate


============================================================
36. API ARCHITECTURE
============================================================

Example public API:

GET /api/pages
GET /api/pages/{slug}

GET /api/news
GET /api/news/{slug}

GET /api/events
GET /api/events/{slug}

GET /api/projects
GET /api/projects/{slug}

GET /api/programs
GET /api/programs/{slug}

GET /api/publications
GET /api/publications/{slug}

GET /api/team
GET /api/partners
GET /api/testimonials
GET /api/menus
GET /api/media
GET /api/galleries
GET /api/media-coverage

GET /api/external/rss/{source}
GET /api/external/facebook/{page}


Admin APIs must be protected.

Public APIs must expose only:

- Approved
- Published
- Public


Use Laravel API Resources.


============================================================
37. LARAVEL ARCHITECTURE
============================================================

Suggested structure:

app/
|
+-- Http/
|   +-- Controllers/
|   +-- Requests/
|   +-- Resources/
|
+-- Models/
+-- Policies/
+-- Services/
+-- Jobs/
+-- Events/
+-- Listeners/
+-- Console/
+-- Providers/
+-- Support/


Services should include areas such as:

- Block Processing
- Publishing
- JSON Import/Export
- Media
- RSS
- External Providers
- Facebook
- Search
- Revisions
- Moderation


Controllers should remain thin.

Business logic belongs in appropriate services/domain layers.


============================================================
38. REACT ARCHITECTURE
============================================================

PUBLIC:

src/
|
+-- api/
+-- components/
|   +-- common/
|   +-- layout/
|   +-- blocks/
|   +-- ui/
+-- pages/
+-- hooks/
+-- services/
+-- contexts/
+-- routes/
+-- utils/
+-- schemas/
+-- types/
+-- styles/


The Block Renderer should remain modular.

Do not create one giant React component.

Builder-specific React components should be isolated from
ordinary public website components.


============================================================
39. AUTHENTICATION / AUTHORIZATION
============================================================

Use:

- Laravel Sanctum
- Spatie Laravel Permission
- Policies
- Gates


Authorization must be enforced server-side.

Never trust frontend permission checks.

Potential roles:

- Super Admin
- Administrator
- Editor
- Author
- Moderator
- Contributor
- Registered User

Final roles and permissions must be reviewed during Phase 1.


============================================================
40. SECURITY
============================================================

Security is a first-class architectural requirement.

Protect against:

- XSS
- SQL Injection
- CSRF
- Broken access control
- Unauthorized publishing
- Unsafe uploads
- SSRF
- XML attacks
- Malicious JSON
- Unsafe external URLs
- Session attacks
- Credential exposure


Requirements:

- Secure password hashing
- CSRF protection
- Rate limiting
- Secure headers
- Request validation
- Authorization policies
- MIME validation
- Upload limits
- Secure storage
- HTML sanitization
- JSON schema validation
- SSRF protection
- Safe XML parsing
- Server-side secrets
- No executable uploaded content


Never expose:

- Secrets
- API keys
- Passwords
- Private credentials
- Internal user information


============================================================
41. PERFORMANCE
============================================================

Backend:

- Database indexes
- Query optimization
- Eager loading
- Pagination
- Caching
- Queue jobs
- Scheduler
- External provider caching
- Avoid N+1


Frontend:

- Code splitting
- Lazy loading
- Optimized images
- Responsive images
- API caching
- Prefetching
- Minimal initial bundle
- Avoid unnecessary requests
- Avoid unnecessary re-renders


External providers must NOT be queried on every visitor request.


============================================================
42. BACKUP ARCHITECTURE
============================================================

Design for:

- Database backup
- Media backup
- Local backup
- Future Dropbox
- Future Google Drive


Credentials must be managed securely using environment/configuration.

Backup functionality must be permission-controlled.


============================================================
43. ACTIVITY LOGGING
============================================================

Track appropriate events such as:

- Login
- Logout
- Create
- Update
- Delete
- Publish
- Unpublish
- Media upload
- Media delete
- Import
- Export
- Settings changes
- User changes
- Role changes
- Permission changes
- RSS sync
- External provider sync
- Testimonial moderation
- Revision restore


Log:

- User
- Action
- Entity
- Entity ID
- Timestamp
- IP where appropriate
- Metadata where appropriate


============================================================
44. ERROR HANDLING
============================================================

Provide consistent handling for:

- Validation errors
- Authentication errors
- Authorization errors
- Not found
- Upload errors
- RSS errors
- Facebook/provider errors
- JSON validation errors
- Missing references
- External service errors


Production must NOT expose stack traces.

Provide:

- User-friendly message
- Actionable message where possible
- Proper logging


============================================================
45. TESTING
============================================================

BACKEND TESTS:

- Authentication
- Authorization
- Roles
- Permissions
- Policies
- Pages
- Nested blocks
- Static content
- Dynamic content
- External content
- Display modes
- Publishing
- Preview
- Revisions
- Media
- Menus
- Testimonials
- Moderation
- Media Coverage
- Galleries
- Providers
- RSS
- Facebook architecture
- JSON import
- JSON export
- API


FRONTEND TESTS:

- Public routing
- Block rendering
- Nested blocks
- Display modes
- Responsive behavior
- API states
- Builder
- Drag/drop
- Repeaters
- JSON import/export
- Preview
- Loading states
- Error states


Run tests after every major phase.


============================================================
46. CODE QUALITY
============================================================

Follow:

- Laravel conventions
- PSR standards
- React conventions
- SOLID where useful
- DRY
- Clean architecture principles


Avoid:

- Giant controllers
- Giant React components
- Duplicate business logic
- Hard-coded content
- Hard-coded secrets
- Unnecessary dependencies
- Premature abstraction
- Unnecessary complexity


Prefer:

- Reusable services
- Reusable components
- Reusable hooks
- API clients
- Validators
- Schemas
- Policies
- Provider interfaces


============================================================
47. DEPLOYMENT MODEL
============================================================

The initial deployment model must remain SIMPLE.

The CMS must be deployable as a standard Laravel project folder.

Primary initial deployment workflow:

1. Build and test the project locally.
2. Create a production-ready ZIP of the project folder.
3. Upload the ZIP to the hosting server.
4. Extract the project.
5. Create the MySQL database and database user.
6. Configure the production .env file.
7. Configure the hosting document root to Laravel's public/
   directory.
8. Install/run required Laravel dependencies and commands.
9. Build or deploy the production React/Vite assets.
10. Configure required Laravel scheduler/queue services where
    applicable.
11. Verify the application.

The deployment must NOT require a custom web installation
wizard.

DO NOT create:

- Web-based /install wizard
- Docker requirement
- Kubernetes requirement
- Specialized deployment platform
- Proprietary deployment mechanism


The application must remain a standard, portable Laravel 12
application.

The architecture should remain compatible with:

- VPS
- CyberPanel
- Nginx
- Apache
- PHP-FPM
- MySQL

Future Git-based deployment may be supported, but it is NOT
required for the initial deployment workflow.


============================================================
48. DEPLOYMENT REQUIREMENTS
============================================================

The project must include:

- .env.example
- Proper Laravel migrations
- Production-safe configuration
- Vite production build
- Composer configuration
- NPM configuration
- Storage configuration
- Queue compatibility
- Scheduler compatibility
- Secure environment configuration


The real production .env must NEVER be committed to Git or
included in the release ZIP.

The release package should NOT include unnecessary development
files such as:

- .git
- node_modules
- Local databases
- IDE files
- Temporary files
- Development caches
- Actual .env secrets


Where possible, dependencies should be installed/built before
creating the production ZIP.

The exact deployment commands will be documented later.

Do NOT spend Phase 1 effort building an installation wizard.


============================================================
49. DEVELOPMENT RULES
============================================================

1. DO NOT blindly generate the entire application.

2. FIRST inspect the existing repository/project.

3. Preserve useful working code.

4. Do not overwrite existing functionality unnecessarily.

5. Do not make silent major architectural decisions.

6. Work phase-by-phase.

7. After every major phase:

   Implement
   ->
   Test
   ->
   Laravel checks
   ->
   Frontend build
   ->
   Migration/API checks
   ->
   Inspect
   ->
   Fix
   ->
   Review
   ->
   Document
   ->
   Continue


8. Do not proceed with critical errors.

9. Never hard-code organizational content.

10. Never hard-code secrets.

11. Never expose unpublished content.

12. Never execute imported JSON code.

13. Never scrape Facebook.

14. Verify current Meta requirements before implementation.

15. Secure RSS against SSRF and XML attacks.

16. Backend authorization is authoritative.

17. Version the JSON schema.

18. Keep blocks extensible.

19. Keep external providers modular.

20. Separate content from presentation.

21. Test every phase.

22. Run production builds.

23. Document major architecture decisions.

24. Prefer simple, maintainable solutions.

25. Do not introduce unnecessary libraries.

26. Do not rewrite working code merely for stylistic reasons.

27. Do not claim a feature is complete until it is implemented
    and tested.


============================================================
50. DEVELOPMENT ROADMAP
============================================================

PHASE 1
ARCHITECTURE REVIEW ONLY

Do NOT build the whole application.

Deliver:

A. Architecture Overview
B. Complete Database ERD/Table Plan
C. Nested Block Engine
D. Static/Dynamic/External Architecture
E. Display Modes
F. Custom Block Builder
G. Templates/Global Blocks
H. Content/Layout/Style/Advanced
I. Responsive System
J. Design Tokens
K. Admin UI Architecture
L. Public React SPA Architecture
M. Menu/Dropdown/Mega Menu
N. Authentication/Authorization
O. External Provider Framework
P. RSS Architecture
Q. Facebook/Meta Architecture
R. Testimonials/Moderation
S. Media Coverage
T. Gallery/Providers
U. AI JSON Schema
V. JSON Import/Export
W. Laravel Architecture
X. API Architecture
Y. Security Architecture
Z. Performance Architecture
AA. Deployment Architecture
AB. Roadmap
AC. Risks and Tradeoffs


Create/update:

CMS-ARCHITECTURE.md
CMS-BLOCK-SCHEMA.md
DATABASE-ARCHITECTURE.md
API-ARCHITECTURE.md
SECURITY-ARCHITECTURE.md
UI-DESIGN-SYSTEM.md
DEPLOYMENT-ARCHITECTURE.md
DEVELOPMENT-ROADMAP.md


At the end create:

PHASE-1-ARCHITECTURE-REVIEW.md


DO NOT start Phase 2 automatically.


============================================================
PHASE 2
FOUNDATION
============================================================

Implement:

- Laravel
- MySQL
- Environment configuration
- Git
- Sanctum
- Authentication
- Spatie Permission
- Roles
- Permissions
- Policies
- Base API
- Bootstrap 5
- Bootstrap Icons
- Design tokens
- Base admin layout
- Base public React application


Then:

Test
Review
Document


============================================================
PHASE 3
CMS CORE
============================================================

Implement:

- Pages
- Workflow
- Publishing
- Preview
- SEO
- Revisions
- Activity logs


============================================================
PHASE 4
BLOCK ENGINE
============================================================

Implement:

- Block definitions
- Nested blocks
- Recursive rendering
- Standard blocks
- Static mode
- Dynamic mode
- External mode
- Display modes
- Responsive behavior
- Content
- Layout
- Style
- Advanced
- Design tokens


============================================================
PHASE 5
CUSTOM BLOCK BUILDER
============================================================

Implement:

- Field builder
- Field types
- Repeaters
- Custom blocks
- Templates
- Global blocks
- Drag/drop
- Duplicate
- Copy/paste
- Hide/show
- Responsive controls
- Live preview


============================================================
PHASE 6
AI JSON
============================================================

Implement:

- JSON schema
- Versioning
- Validation
- Security validation
- Import
- Export
- Preview
- Import report
- Asset validation
- Missing reference handling


============================================================
PHASE 7
MEDIA
============================================================

Implement:

- Media library
- Upload
- Metadata
- Search
- Filtering
- Preview
- Replacement
- Secure storage


============================================================
PHASE 8
CONTENT MODULES
============================================================

Implement:

- News
- Events
- Projects
- Programs
- Publications
- Team
- Partners
- Testimonials
- Testimonial moderation
- Media Coverage
- Galleries


============================================================
PHASE 9
NAVIGATION
============================================================

Implement:

- Header
- Footer
- Menu Builder
- Dropdown
- Multi-level menu
- Mega Menu
- Mobile navigation


============================================================
PHASE 10
EXTERNAL PROVIDERS
============================================================

Implement:

- Provider abstraction
- RSS consumption
- RSS distribution
- RSS security
- RSS caching
- RSS filtering
- Facebook/Meta integration
- Gallery providers
- External synchronization


============================================================
PHASE 11
PUBLIC REACT SPA
============================================================

Implement:

- Homepage
- Page rendering
- Block rendering
- Dynamic content
- Search
- Navigation
- SEO
- Structured data
- Galleries
- RSS
- External content
- API caching
- Lazy loading
- Code splitting
- Responsive design
- Accessibility


============================================================
PHASE 12
SECURITY / TESTING
============================================================

Perform:

- Security audit
- Authentication audit
- Authorization audit
- Upload audit
- JSON import audit
- SSRF audit
- XML security audit
- XSS audit
- API audit
- External provider audit


============================================================
PHASE 13
PERFORMANCE
============================================================

Review and optimize:

- Database
- Queries
- Indexes
- Caching
- API
- Frontend bundle
- Images
- Provider caching
- N+1 queries
- Rendering
- Network requests


============================================================
PHASE 14
PRODUCTION PREPARATION
============================================================

Prepare:

- Production configuration
- Deployment documentation
- Environment documentation
- Database migrations
- Backup/restore
- Queue
- Scheduler
- Storage
- Security checklist
- Administrator documentation
- Developer documentation
- ZIP release procedure


Do NOT build a custom installation wizard.

The expected deployment remains:

PROJECT ZIP
    ->
UPLOAD
    ->
EXTRACT
    ->
CREATE DATABASE
    ->
CONFIGURE .env
    ->
CONFIGURE HOSTING
    ->
RUN REQUIRED COMMANDS
    ->
VERIFY


============================================================
51. PHASE COMPLETION REPORT
============================================================

At the end of EVERY phase produce:

PHASE:
STATUS:

IMPLEMENTED:

FILES CREATED:

FILES MODIFIED:

DATABASE CHANGES:

API CHANGES:

UI CHANGES:

TESTS:

BUILD:

SECURITY CHECK:

PERFORMANCE CHECK:

DEPLOYMENT IMPACT:

KNOWN ISSUES:

ARCHITECTURAL DECISIONS:

NEXT PHASE:


Never claim complete if the work has not actually been
implemented and tested.


============================================================
52. FINAL SYSTEM VISION
============================================================

                    PROBHA AURORA CMS
                            |
                     Laravel 12 Core
                            |
              +-------------+-------------+
              |             |             |
           CONTENT         BLOCKS        USERS
              |             |             |
         News/Events   Nested Blocks    Admins
         Projects      Static           Editors
         Programs      Dynamic          Authors
         Publications  External         Moderators
         Team          Templates        Users
         Partners      Global
         Testimonials  AI/JSON
         Media Coverage
         Galleries
              |             |
              +-------------+-------------+
                            |
                    VISUAL BUILDER
                            |
               +------------+------------+
               |            |            |
             Layout        Style      Responsive
               |            |            |
               +------------+------------+
                            |
                  HEADER / MENU / FOOTER
                            |
              +-------------+-------------+
              |             |             |
             RSS         Facebook       APIs
              |
       Other Websites
                            |
                    JSON INTERCHANGE
                            |
              +-------------+-------------+
              |             |             |
           ChatGPT        Claude        Gemini
                            |
                    PUBLIC REACT SPA
                            |
                       ORGANIZATION
                         WEBSITE


============================================================
53. FINAL ARCHITECTURAL PRINCIPLE
============================================================

The CMS stores:

- Structured content
- Block definitions
- Data source configuration
- Display configuration
- Layout configuration
- Style configuration
- Responsive configuration
- Design token references


The architecture is:

Block Engine
    =
Structure


Data Source System
    =
Where content comes from


Display System
    =
How content is presented


Layout System
    =
Positioning and dimensions


Style System
    =
Visual appearance


Responsive System
    =
Breakpoint behavior


Design Token System
    =
Global visual identity


Menu Engine
    =
Navigation


Moderation System
    =
User-generated content


Provider System
    =
External integrations


JSON System
    =
Portability and AI interoperability


Laravel Backend
    =
Data + Security + Business Logic + API


Admin Laravel UI
    =
Content Administration


React SPA
    =
Public Visitor Experience


Deployment
    =
Standard portable Laravel project
    +
ZIP release
    +
Hosting configuration
    +
MySQL database
    +
.env configuration


============================================================
54. START NOW
============================================================

DO NOT BUILD THE WHOLE APPLICATION IMMEDIATELY.


FIRST:

1. Inspect the existing repository/project.

2. Report:
   - Laravel version
   - PHP version
   - React version
   - Vite version
   - Installed packages
   - Database configuration
   - Existing routes
   - Existing authentication
   - Existing UI
   - Existing API
   - Existing reusable code
   - Existing migrations
   - Existing models
   - Existing controllers
   - Existing React components
   - Existing services
   - Existing configuration


3. Identify conflicts.

4. Identify reusable code.

5. Identify missing architecture.

6. Produce Phase 1 architecture.

7. Design database.

8. Design nested Block Engine.

9. Design Content Source system.

10. Design Display Mode system.

11. Design Custom Block Builder.

12. Design Design Token system.

13. Design Admin UI.

14. Design Public React SPA.

15. Design Provider system.

16. Design RSS.

17. Design Facebook/Meta integration architecture.

18. Design Gallery.

19. Design Media Coverage.

20. Design Testimonials/Moderation.

21. Design AI JSON schema.

22. Design JSON Import/Export.

23. Design API.

24. Design Security.

25. Design Performance.

26. Design simple ZIP-based Deployment Architecture.

27. Create/update Phase 1 documentation.

28. Review the architecture for:

    - Contradictions
    - Unnecessary complexity
    - Duplicate systems
    - Scalability problems
    - Security risks
    - Performance risks
    - Maintainability problems


29. Produce:

PHASE-1-ARCHITECTURE-REVIEW.md


30. STOP.

DO NOT START PHASE 2 AUTOMATICALLY.

WAIT FOR ARCHITECTURE REVIEW / APPROVAL BEFORE
PROCEEDING TO PHASE 2.


============================================================
55. FINAL OBJECTIVE
============================================================

Build a secure, maintainable, extensible, high-performance
Probha Aurora CMS that can evolve for many years without
major architectural rewrites.

Prioritize:

1. Architecture quality
2. Security
3. Maintainability
4. Extensibility
5. Performance
6. Accessibility
7. Reusability
8. Developer experience
9. Administrator experience
10. Public visitor experience


The goal is not to generate the largest amount of code.

The goal is to build the RIGHT architecture and then
implement it carefully, incrementally, and testably.

Keep deployment simple.

Keep the application portable.

Do not introduce infrastructure complexity unless there is
a clear architectural reason.

============================================================
END OF FINAL MASTER PROJECT PROMPT
============================================================
```
