<?php

use App\Auth\PermissionCatalog;
use App\Auth\RolePermissionSynchronizer;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

it('seeds every catalogued permission and role', function () {
    expect(Permission::count())->toBe(count(PermissionCatalog::all()))
        ->and(Role::pluck('name')->sort()->values()->all())
        ->toBe(collect(array_keys(PermissionCatalog::roles()))->sort()->values()->all());
});

it('is idempotent and keeps permissions an administrator removed', function () {
    Role::findByName('editor')->revokePermissionTo('news.publish');

    $result = app(RolePermissionSynchronizer::class)->sync();

    expect($result['permissions_created'])->toBeEmpty()
        ->and(Role::findByName('editor')->hasPermissionTo('news.publish'))->toBeFalse();
});

it('grants the super admin every ability through the gate', function () {
    $user = userWithRole('super-admin');

    expect($user->can('users.manage_roles'))->toBeTrue()
        ->and($user->can('any.future.ability'))->toBeTrue();
});

it('enforces the screen-level permission matrix', function (string $role, string $route, int $status) {
    $this->actingAs(userWithRole($role))->get(route($route))->assertStatus($status);
})->with([
    ['administrator', 'admin.users.index', 200],
    ['administrator', 'admin.roles.index', 403],
    ['administrator', 'admin.settings.general', 200],
    ['administrator', 'admin.design.tokens', 200],
    ['administrator', 'admin.activity.index', 200],
    ['editor', 'admin.users.index', 403],
    ['editor', 'admin.settings.general', 403],
    ['editor', 'admin.design.tokens', 403],
    ['editor', 'admin.activity.index', 403],
    ['author', 'admin.dashboard', 200],
    ['author', 'admin.users.index', 403],
    ['moderator', 'admin.dashboard', 200],
    ['moderator', 'admin.settings.general', 403],
    ['super-admin', 'admin.roles.index', 200],
]);

it('only shows navigation the user is allowed to use', function () {
    $this->actingAs(userWithRole('editor'))
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertDontSee('Roles &amp; Permissions', false)
        ->assertDontSee(route('admin.users.index'));
});
