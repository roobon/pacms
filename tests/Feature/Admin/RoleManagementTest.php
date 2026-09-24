<?php

use App\Models\ActivityLog;
use Spatie\Permission\Models\Role;

it('lets the super admin change a role and logs the difference', function () {
    $this->actingAs(userWithRole('super-admin'))
        ->put(route('admin.roles.update', Role::findByName('moderator')), [
            'permissions' => ['admin.access', 'dashboard.view', 'testimonials.view'],
        ])
        ->assertRedirect(route('admin.roles.index'));

    $moderator = Role::findByName('moderator');
    expect($moderator->hasPermissionTo('testimonials.moderate'))->toBeFalse();

    $log = ActivityLog::where('action', 'security.role_permissions_changed')->latest('id')->firstOrFail();
    expect($log->properties['removed'])->toContain('testimonials.moderate');
});

it('keeps the super admin role locked', function () {
    $superAdmin = userWithRole('super-admin');

    $this->actingAs($superAdmin)->get(route('admin.roles.edit', Role::findByName('super-admin')))->assertForbidden();
    $this->actingAs($superAdmin)->put(route('admin.roles.update', Role::findByName('super-admin')), ['permissions' => []])->assertForbidden();
});

it('rejects unknown permissions', function () {
    $this->actingAs(userWithRole('super-admin'))
        ->put(route('admin.roles.update', Role::findByName('author')), ['permissions' => ['system.root']])
        ->assertSessionHasErrors('permissions.0');
});
