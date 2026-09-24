<?php

use App\Models\User;

it('creates a super admin from the command line', function () {
    $this->artisan('pacms:create-admin', [
        '--name' => 'Site Owner',
        '--email' => 'Owner@Example.org',
        '--password' => 'correct-horse-42',
    ])->assertSuccessful();

    $user = User::where('email', 'owner@example.org')->firstOrFail();
    expect($user->isSuperAdmin())->toBeTrue()
        ->and($user->hasVerifiedEmail())->toBeTrue();
});

it('refuses weak passwords and duplicate e-mails', function () {
    $this->artisan('pacms:create-admin', ['--name' => 'A', '--email' => 'a@example.org', '--password' => 'short'])
        ->assertFailed();

    User::factory()->create(['email' => 'taken@example.org']);
    $this->artisan('pacms:create-admin', ['--name' => 'B', '--email' => 'taken@example.org', '--password' => 'correct-horse-42'])
        ->assertFailed();
});

it('runs the doctor checks', function () {
    // The test environment has no cron heartbeat or build, so only check that it runs and reports.
    $this->artisan('pacms:doctor')
        ->expectsOutputToContain('Database')
        ->expectsOutputToContain('Roles & permissions');
});
