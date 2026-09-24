<?php

use App\Services\Settings\SettingsService;

it('returns public site data only', function () {
    app(SettingsService::class)->set('design', ['tokens' => ['color.primary' => '#111111']]);

    $response = $this->getJson('/api/v1/site')
        ->assertOk()
        ->assertJsonStructure(['data' => ['name', 'tagline', 'contact' => ['email'], 'theme' => ['stylesheet'], 'features' => ['registration']]]);

    // Private settings (raw token overrides) are not exposed.
    expect($response->getContent())->not->toContain('#111111');
});

it('rejects guests on the account endpoint with the error envelope', function () {
    $this->getJson('/api/v1/me')
        ->assertUnauthorized()
        ->assertExactJson(['message' => 'Unauthenticated.', 'code' => 'unauthenticated']);
});

it('returns only safe fields for the signed-in user', function () {
    $user = userWithRole('registered-user', twoFactor: false);

    $response = $this->actingAs($user)->getJson('/api/v1/me')->assertOk();

    expect(array_keys($response->json('data')))->toBe(['id', 'name', 'email', 'email_verified', 'can_access_admin'])
        ->and($response->json('data.can_access_admin'))->toBeFalse();
});

it('returns the JSON 404 envelope for unknown API routes', function () {
    $this->getJson('/api/v1/does-not-exist')
        ->assertNotFound()
        ->assertJsonPath('code', 'not_found');

    $this->get('/api/v1/does-not-exist')->assertNotFound()->assertJsonPath('code', 'not_found');
});

it('sends security headers with a CSP nonce', function () {
    $response = $this->get('/');

    $csp = $response->headers->get('Content-Security-Policy');
    expect($csp)->toMatch("/script-src 'self' 'nonce-[A-Za-z0-9]+'/")
        ->toContain("object-src 'none'")
        ->toContain("frame-ancestors 'self'");

    $response->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
});

it('rate-limits the public API', function () {
    foreach (range(1, 120) as $i) {
        $this->getJson('/api/v1/site');
    }

    $this->getJson('/api/v1/site')->assertStatus(429)->assertJsonPath('code', 'too_many_requests');
});
