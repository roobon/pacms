<?php

use App\Auth\PermissionCatalog;

it('has unique permission names', function () {
    $all = PermissionCatalog::all();

    expect($all)->toHaveCount(count(array_unique($all)));
});

it('only assigns catalogued permissions to default roles', function () {
    foreach (PermissionCatalog::roles() as $role => $definition) {
        expect(array_diff($definition['permissions'], PermissionCatalog::all()))
            ->toBeEmpty("Role {$role} references unknown permissions");
    }
});

it('never gives registered users admin access', function () {
    expect(PermissionCatalog::roles()[PermissionCatalog::REGISTERED_USER]['permissions'])->toBeEmpty();
});

it('keeps role management with the super admin only by default', function () {
    foreach (PermissionCatalog::roles() as $role => $definition) {
        if ($role !== PermissionCatalog::SUPER_ADMIN) {
            expect($definition['permissions'])->not->toContain('users.manage_roles');
        }
    }
});

it('does not let contributors approve or publish', function () {
    $permissions = PermissionCatalog::roles()['contributor']['permissions'];

    expect($permissions)->not->toContain('pages.publish')
        ->and($permissions)->not->toContain('news.approve')
        ->and($permissions)->toContain('pages.submit');
});
