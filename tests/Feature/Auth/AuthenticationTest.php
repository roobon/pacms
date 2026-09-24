<?php

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

it('shows the sign-in page', function () {
    $this->get('/auth/login')->assertOk()->assertSee('Sign in');
});

it('redirects guests from the admin to the sign-in page', function () {
    $this->get('/admin')->assertRedirect('/auth/login');
});

it('signs in a staff user with 2FA via the two-factor challenge', function () {
    $user = userWithRole('editor');

    $this->post('/auth/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect('/auth/two-factor-challenge');

    $this->assertGuest();
});

it('signs in a user without 2FA and records the login', function () {
    $user = userWithRole('author', twoFactor: false);

    $this->post('/auth/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('admin.dashboard'));

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->last_login_at)->not->toBeNull()
        ->and(ActivityLog::where('action', 'auth.login')->where('user_id', $user->id)->exists())->toBeTrue();
});

it('rejects wrong passwords and logs the failure without the password', function () {
    $user = userWithRole('author', twoFactor: false);

    $this->post('/auth/login', ['email' => $user->email, 'password' => 'wrong-password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
    $log = ActivityLog::where('action', 'auth.failed')->latest('id')->first();
    expect($log->properties)->toBe(['email' => $user->email])
        ->and(json_encode($log->properties))->not->toContain('wrong-password');
});

it('blocks suspended accounts even with the correct password', function () {
    $user = User::factory()->suspended()->create();
    $user->assignRole('author');

    $this->post('/auth/login', ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('logs out a user who is suspended during their session', function () {
    $user = userWithRole('author', twoFactor: false);
    $this->actingAs($user);
    $user->forceFill(['status' => 'suspended'])->save();

    $this->get('/admin')->assertForbidden();
    $this->assertGuest();
});

it('throttles repeated failed logins', function () {
    $user = userWithRole('author', twoFactor: false);

    foreach (range(1, 5) as $attempt) {
        $this->post('/auth/login', ['email' => $user->email, 'password' => 'wrong']);
    }

    $this->post('/auth/login', ['email' => $user->email, 'password' => 'password'])
        ->assertStatus(429);

    $this->assertGuest();
    RateLimiter::clear('login');
});

it('returns JSON for SPA logins', function () {
    $user = userWithRole('registered-user', twoFactor: false);

    $this->postJson('/auth/login', ['email' => $user->email, 'password' => 'password'])
        ->assertOk()
        ->assertJson(['two_factor' => false]);
});
