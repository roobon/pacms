<x-admin.layout title="Roles & Permissions">
    <x-admin.page-header title="Roles & Permissions" subtitle="Permissions are checked on the server for every action. Changes take effect immediately." />

    <div class="card pa-card">
        <div class="table-responsive">
            <table class="table pa-table align-middle mb-0">
                <caption class="visually-hidden">Roles</caption>
                <thead>
                    <tr>
                        <th scope="col">Role</th>
                        <th scope="col">Users</th>
                        <th scope="col">Permissions</th>
                        <th scope="col"><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($roles as $role)
                        <tr>
                            <td class="fw-semibold">{{ \App\Auth\PermissionCatalog::roleLabel($role->name) }}</td>
                            <td>{{ $role->users_count }}</td>
                            <td>
                                @if ($role->name === \App\Auth\PermissionCatalog::SUPER_ADMIN)
                                    All (always)
                                @else
                                    {{ $role->permissions_count }}
                                @endif
                            </td>
                            <td class="text-end">
                                @unless ($role->name === \App\Auth\PermissionCatalog::SUPER_ADMIN)
                                    <a href="{{ route('admin.roles.edit', $role) }}" class="btn btn-sm btn-outline-secondary">Edit permissions<span class="visually-hidden"> for {{ \App\Auth\PermissionCatalog::roleLabel($role->name) }}</span></a>
                                @endunless
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-admin.layout>
