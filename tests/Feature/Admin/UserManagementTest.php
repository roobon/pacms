<?php

use App\Models\ActivityLog;
use App\Models\User;
use Spatie\Permission\Models\Role;

function userPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'New Staff',
        'email' => 'new.staff@example.org',
        'password' => 'correct-horse-42',
        'password_confirmation' => 'correct-horse-42',
        'status' => 'active',
        'roles' => ['editor'],
    ], $overrides);
}

it('lets an administrator create a verified staff user and logs it', function () {
    $admin = userWithRole('administrator');

    $this->actingAs($admin)
        ->post(route('admin.users.store'), userPayload())
        ->assertRedirect(route('admin.users.index'));

    $user = User::where('email', 'new.staff@example.org')->firstOrFail();
    expect($user->hasRole('editor'))->toBeTrue()
        ->and($user->hasVerifiedEmail())->toBeTrue();

    $log = ActivityLog::where('action', 'user.created')->where('subject_id', $user->id)->firstOrFail();
    expect($log->user_id)->toBe($admin->id)
        ->and(json_encode($log->properties))->not->toContain('correct-horse-42');
});

it('prevents administrators from granting the super admin role', function () {
    $this->actingAs(userWithRole('administrator'))
        ->post(route('admin.users.store'), userPayload(['roles' => ['super-admin']]))
        ->assertSessionHasErrors('roles');

    expect(User::where('email', 'new.staff@example.org')->exists())->toBeFalse();
});

it('prevents administrators from editing super admins', function () {
    $superAdmin = userWithRole('super-admin');

    $this->actingAs(userWithRole('administrator'))
        ->get(route('admin.users.edit', $superAdmin))
        ->assertForbidden();
});

it('prevents users from assigning roles with permissions they do not hold', function () {
    $role = Role::findByName('author');
    $role->givePermissionTo(['users.view', 'users.manage']);

    $this->actingAs(userWithRole('author', twoFactor: false))
        ->post(route('admin.users.store'), userPayload(['roles' => ['administrator']]))
        ->assertSessionHasErrors('roles');
});

it('never demotes or deletes the last active super admin', function () {
    $only = userWithRole('super-admin');

    $this->actingAs($only)
        ->put(route('admin.users.update', $only), userPayload([
            'name' => $only->name, 'email' => $only->email, 'password' => '', 'password_confirmation' => '',
            'roles' => ['editor'],
        ]))
        ->assertSessionHasErrors('roles');

    expect($only->fresh()->isSuperAdmin())->toBeTrue();
});

it('does not allow suspending or deleting yourself', function () {
    $admin = userWithRole('administrator');

    $this->actingAs($admin)
        ->put(route('admin.users.update', $admin), userPayload([
            'name' => $admin->name, 'email' => $admin->email, 'password' => '', 'password_confirmation' => '',
            'roles' => ['administrator'], 'status' => 'suspended',
        ]))
        ->assertSessionHasErrors('status');

    $this->actingAs($admin)->delete(route('admin.users.destroy', $admin))->assertForbidden();
});

it('suspends users and logs role changes', function () {
    $target = userWithRole('author', twoFactor: false);

    $this->actingAs(userWithRole('administrator'))
        ->put(route('admin.users.update', $target), userPayload([
            'name' => $target->name, 'email' => $target->email, 'password' => '', 'password_confirmation' => '',
            'roles' => ['contributor'], 'status' => 'suspended',
        ]))
        ->assertRedirect(route('admin.users.index'));

    $target->refresh();
    expect($target->isActive())->toBeFalse()
        ->and($target->getRoleNames()->all())->toBe(['contributor'])
        ->and(ActivityLog::where('action', 'user.roles_changed')->where('subject_id', $target->id)->exists())->toBeTrue();
});

it('soft-deletes users', function () {
    $target = userWithRole('author', twoFactor: false);

    $this->actingAs(userWithRole('administrator'))
        ->delete(route('admin.users.destroy', $target))
        ->assertRedirect(route('admin.users.index'));

    expect(User::find($target->id))->toBeNull()
        ->and(User::withTrashed()->find($target->id))->not->toBeNull();
});

it('renders the create and edit forms', function () {
    $admin = userWithRole('administrator');
    $target = userWithRole('author', twoFactor: false);

    $this->actingAs($admin)->get(route('admin.users.create'))->assertOk()->assertSee('Create user');
    $this->actingAs($admin)->get(route('admin.users.edit', $target))->assertOk()->assertSee($target->email);
});

it('lists and filters users', function () {
    userWithRole('author', twoFactor: false)->update(['name' => 'Findable Person']);

    $this->actingAs(userWithRole('administrator'))
        ->get(route('admin.users.index', ['q' => 'Findable']))
        ->assertOk()
        ->assertSee('Findable Person');
});
