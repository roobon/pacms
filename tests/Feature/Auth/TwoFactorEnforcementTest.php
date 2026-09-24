<?php

it('sends privileged staff without 2FA to the security page', function (string $role) {
    $this->actingAs(userWithRole($role, twoFactor: false))
        ->get('/admin')
        ->assertRedirect(route('admin.account.security'));
})->with(['super-admin', 'administrator', 'editor']);

it('lets privileged staff open the security page to set up 2FA', function () {
    $this->actingAs(userWithRole('editor', twoFactor: false))
        ->get(route('admin.account.security'))
        ->assertOk()
        ->assertSee('Enable two-factor authentication');
});

it('allows privileged staff with confirmed 2FA', function () {
    $this->actingAs(userWithRole('administrator'))
        ->get('/admin')
        ->assertOk();
});

it('does not require 2FA for other staff roles', function (string $role) {
    $this->actingAs(userWithRole($role, twoFactor: false))
        ->get('/admin')
        ->assertOk();
})->with(['author', 'contributor', 'moderator']);

it('can be switched off by configuration', function () {
    config(['pacms.security.enforce_two_factor' => false]);

    $this->actingAs(userWithRole('editor', twoFactor: false))
        ->get('/admin')
        ->assertOk();
});
