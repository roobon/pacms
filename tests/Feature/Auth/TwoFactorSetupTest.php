<?php

use App\Models\ActivityLog;
use App\Models\User;
use PragmaRX\Google2FA\Google2FA;

/**
 * End-to-end: enable 2FA from the admin Security page, confirm it with an
 * authenticator code, then sign in again through the two-factor challenge.
 */
it('lets a user enable, confirm and then sign in with two-factor authentication', function () {
    config(['pacms.security.enforce_two_factor' => false]);
    $user = userWithRole('author', twoFactor: false);
    $this->actingAs($user);

    // Opening the security page asks for the password first (sudo mode).
    $this->get(route('admin.account.security'))->assertRedirect(route('password.confirm'));
    $this->post('/auth/user/confirm-password', ['password' => 'password'])
        ->assertRedirect(route('admin.account.security'));

    $this->get(route('admin.account.security'))->assertOk()->assertSee('Enable two-factor authentication');

    // Enable → the page shows the QR code.
    $this->from(route('admin.account.security'))
        ->post('/auth/user/two-factor-authentication')
        ->assertRedirect(route('admin.account.security'));

    $user->refresh();
    expect($user->two_factor_secret)->not->toBeNull();
    $this->get(route('admin.account.security'))->assertOk()->assertSee('Scan this QR code');

    // Confirm with a code from the authenticator app.
    $code = app(Google2FA::class)->getCurrentOtp(decrypt($user->two_factor_secret));
    $this->from(route('admin.account.security'))
        ->post('/auth/user/confirmed-two-factor-authentication', ['code' => $code])
        ->assertRedirect(route('admin.account.security'))
        ->assertSessionHas('status', 'two-factor-authentication-confirmed');

    expect($user->fresh()->hasConfirmedTwoFactor())->toBeTrue()
        ->and(ActivityLog::where('action', 'security.two_factor_enabled')->where('user_id', $user->id)->exists())->toBeTrue();

    // Sign out, sign in: password first, then the two-factor challenge.
    $this->post('/auth/logout');
    $this->assertGuest();

    $this->post('/auth/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect('/auth/two-factor-challenge');
    $this->get('/auth/two-factor-challenge')->assertOk()->assertSee('Authentication code');

    $recoveryCode = $user->fresh()->recoveryCodes()[0];
    $this->post('/auth/two-factor-challenge', ['recovery_code' => $recoveryCode])
        ->assertRedirect(route('admin.dashboard'));

    $this->assertAuthenticatedAs($user);
});

it('rejects a wrong two-factor code at sign-in', function () {
    $user = User::factory()->withTwoFactor()->create();
    $user->assignRole('author');

    $this->post('/auth/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect('/auth/two-factor-challenge');

    $this->post('/auth/two-factor-challenge', ['code' => '000000'])
        ->assertRedirect('/auth/two-factor-challenge')
        ->assertSessionHasErrors('code');

    $this->assertGuest();
});
