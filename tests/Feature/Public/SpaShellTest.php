<?php

use App\Services\Settings\SettingsService;

it('serves the homepage shell with server-rendered SEO and initial data', function () {
    app(SettingsService::class)->set('site', ['name' => 'Green Future Foundation', 'description' => 'We support schools.']);

    $this->get('/')
        ->assertOk()
        ->assertSee('<title data-pacms-head>Green Future Foundation</title>', false)
        ->assertSee('<meta name="description" content="We support schools." data-pacms-head>', false)
        ->assertSee('<link rel="canonical" href="http://pacms.test/" data-pacms-head>', false)
        ->assertSee('<meta property="og:site_name" content="Green Future Foundation">', false)
        ->assertSee('"@type":"Organization"', false)
        ->assertSee('id="pacms-initial"', false);
});

it('returns a real 404 status for unknown pages', function () {
    $this->get('/no-such-page')
        ->assertNotFound()
        ->assertSee('noindex', false)
        ->assertDontSee('rel="canonical"', false);
});

it('serves the account pages without indexing', function () {
    $this->get('/account/login')->assertOk()->assertSee('content="noindex,follow"', false);
});

it('does not serve the SPA under server-owned paths', function () {
    $this->get('/admin/unknown-page')->assertNotFound()->assertDontSee('pacms-initial');
    $this->get('/storage/nothing-here.txt')->assertNotFound()->assertDontSee('pacms-initial');
});

it('encodes the initial payload so settings cannot break out of the script tag', function () {
    app(SettingsService::class)->set('site', ['name' => '</script><script>alert(1)</script>']);

    $html = $this->get('/')->getContent();

    expect($html)->not->toContain('</script><script>alert(1)')
        ->and($html)->toContain('</script>');
});
