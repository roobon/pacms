<?php

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;

it('registers public users as Registered Users only and sends verification', function () {
    Notification::fake();

    $this->postJson('/auth/register', [
        'name' => 'Visitor',
        'email' => 'Visitor@Example.org',
        'password' => 'correct-horse-42',
        'password_confirmation' => 'correct-horse-42',
    ])->assertCreated();

    $user = User::where('email', 'visitor@example.org')->firstOrFail();

    expect($user->getRoleNames()->all())->toBe(['registered-user'])
        ->and($user->hasVerifiedEmail())->toBeFalse()
        ->and($user->can('admin.access'))->toBeFalse();

    Notification::assertSentTo($user, VerifyEmail::class);
});

it('ignores attempts to self-assign roles or status during registration', function () {
    $this->postJson('/auth/register', [
        'name' => 'Sneaky',
        'email' => 'sneaky@example.org',
        'password' => 'correct-horse-42',
        'password_confirmation' => 'correct-horse-42',
        'roles' => ['super-admin'],
        'status' => 'active',
        'email_verified_at' => now()->toIso8601String(),
    ])->assertCreated();

    $user = User::where('email', 'sneaky@example.org')->firstOrFail();

    expect($user->isSuperAdmin())->toBeFalse()
        ->and($user->hasVerifiedEmail())->toBeFalse();
});

it('enforces the password policy', function () {
    $this->postJson('/auth/register', [
        'name' => 'Weak',
        'email' => 'weak@example.org',
        'password' => 'short',
        'password_confirmation' => 'short',
    ])->assertUnprocessable()
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors('password');
});

it('denies registered users access to the admin', function () {
    $this->actingAs(userWithRole('registered-user', twoFactor: false))
        ->get('/admin')
        ->assertForbidden();
});
