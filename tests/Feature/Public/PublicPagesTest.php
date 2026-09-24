<?php

use App\Enums\WorkflowAction;
use App\Models\Redirect;
use App\Services\Pages\PageService;
use App\Services\Publishing\PublishingService;
use App\Services\Settings\SettingsService;

it('renders SEO, Open Graph and breadcrumbs for a live page in the server shell', function () {
    app(SettingsService::class)->set('site', ['name' => 'Green Future']);
    $parent = livePage(['title' => 'About', 'excerpt' => 'Who we are']);
    livePage(['title' => 'Team', 'excerpt' => 'Meet the team', 'parent_id' => $parent->id, 'seo' => ['og_title' => 'Our people']]);

    $this->get('/about/team')
        ->assertOk()
        ->assertSee('<title data-pacms-head>Team · Green Future</title>', false)
        ->assertSee('<meta name="description" content="Meet the team" data-pacms-head>', false)
        ->assertSee('<link rel="canonical" href="http://pacms.test/about/team" data-pacms-head>', false)
        ->assertSee('<meta property="og:title" content="Our people" data-pacms-head>', false)
        ->assertSee('"@type":"BreadcrumbList"', false)
        ->assertSee('"page":{"type":"page"', false);
});

it('honours noindex and custom SEO titles', function () {
    livePage(['title' => 'Hidden', 'seo' => ['title' => 'Custom SEO title', 'robots_index' => false, 'robots_follow' => true]]);

    $this->get('/hidden')
        ->assertSee('<title data-pacms-head>Custom SEO title</title>', false)
        ->assertSee('content="noindex,follow"', false);
});

it('serves the chosen homepage at / and redirects its own address there', function () {
    $home = livePage(['title' => 'Welcome home', 'excerpt' => 'Hello']);
    app(SettingsService::class)->set('site', ['homepage_page_id' => $home->id]);

    $this->get('/')->assertOk()->assertSee('Welcome home')->assertSee('<link rel="canonical" href="http://pacms.test/" data-pacms-head>', false);
    $this->get('/welcome-home')->assertRedirect('/')->assertStatus(301);
    $this->getJson('/api/v1/resolve?path=%2F')->assertJsonPath('kind', 'page')->assertJsonPath('data.is_home', true);
});

it('resolves paths through the API with the same rules as the shell', function () {
    livePage(['title' => 'Contact']);
    Redirect::create(['source_path' => '/old-contact', 'target_path' => '/contact', 'status_code' => 301]);

    $this->getJson('/api/v1/resolve?path=/contact')->assertOk()->assertJsonPath('kind', 'page')->assertJsonPath('data.path', '/contact');
    $this->getJson('/api/v1/resolve?path=/old-contact')->assertOk()->assertJson(['kind' => 'redirect', 'to' => '/contact', 'status' => 301]);
    $this->getJson('/api/v1/resolve?path=%2F')->assertOk()->assertJsonPath('kind', 'home');
    $this->getJson('/api/v1/resolve?path=/nothing')->assertNotFound()->assertJsonPath('kind', 'not_found');
});

it('sends public cache headers on page API responses', function () {
    livePage(['title' => 'Cached']);

    $response = $this->getJson('/api/v1/pages/cached')->assertOk();
    expect($response->headers->get('Cache-Control'))->toContain('public')->toContain('max-age=60')
        ->and($response->headers->get('ETag'))->not->toBeNull();
});

it('does not leak working-copy fields or internal data in the page payload', function () {
    $page = livePage(['title' => 'Public title']);
    app(PageService::class)->update(userWithRole('editor'), $page->fresh(), ['title' => 'Private draft title', 'template' => 'default'], $page->fresh()->lock_version);

    $json = $this->getJson('/api/v1/pages/public-title')->assertOk()->json('data');

    expect($json['title'])->toBe('Public title')
        ->and(json_encode($json))->not->toContain('Private draft title')
        ->and(array_keys($json))->not->toContain('author_id', 'lock_version', 'created_by', 'status');
});

it('lists only live, indexable pages in the sitemap', function () {
    livePage(['title' => 'Visible']);
    livePage(['title' => 'No index', 'seo' => ['robots_index' => false, 'robots_follow' => true]]);
    makePage(userWithRole('editor'), ['title' => 'Draft only']);

    $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->assertSee('/sitemaps/pages.xml', false);

    $xml = $this->get('/sitemaps/pages.xml')->assertOk()->getContent();
    expect($xml)->toContain('http://pacms.test/visible')
        ->not->toContain('no-index')
        ->not->toContain('draft-only');
});

it('serves robots.txt from settings with the sitemap line', function () {
    app(SettingsService::class)->set('seo', ['robots_txt' => "User-agent: *\nDisallow: /private"]);

    $this->get('/robots.txt')->assertOk()
        ->assertSee('Disallow: /private')
        ->assertSee('Sitemap: http://pacms.test/sitemap.xml');
});

it('invalidates cached page payloads when content changes', function () {
    $editor = userWithRole('editor');
    $page = livePage(['title' => 'Before']);
    $this->getJson('/api/v1/pages/before')->assertJsonPath('data.title', 'Before');

    app(PageService::class)->update($editor, $page->fresh(), ['title' => 'After', 'template' => 'default'], $page->fresh()->lock_version);
    app(PublishingService::class)->transition($page->fresh(), WorkflowAction::Publish, $editor);

    $this->getJson('/api/v1/pages/before')->assertJsonPath('data.title', 'After');
});
