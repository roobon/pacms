<x-admin.layout title="Users">
    <x-admin.page-header title="Users" subtitle="Staff accounts and registered users.">
        @can('create', \App\Models\User::class)
            <x-slot:actions>
                <a href="{{ route('admin.users.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg" aria-hidden="true"></i> Add user</a>
            </x-slot:actions>
        @endcan
    </x-admin.page-header>

    <form method="GET" class="pa-filters" role="search" aria-label="Filter users">
        <div>
            <label for="filter-q" class="form-label">Search</label>
            <input id="filter-q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" placeholder="Name or e-mail">
        </div>
        <div>
            <label for="filter-role" class="form-label">Role</label>
            <select id="filter-role" name="role" class="form-select">
                <option value="">All roles</option>
                @foreach ($roles as $role)
                    <option value="{{ $role }}" @selected(($filters['role'] ?? '') === $role)>{{ \App\Auth\PermissionCatalog::roleLabel($role) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="filter-status" class="form-label">Status</label>
            <select id="filter-status" name="status" class="form-select">
                <option value="">Any status</option>
                @foreach (\App\Enums\UserStatus::cases() as $status)
                    <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="pa-filters__actions">
            <button class="btn btn-secondary" type="submit">Filter</button>
            @if (array_filter($filters))
                <a href="{{ route('admin.users.index') }}" class="btn btn-link">Reset</a>
            @endif
        </div>
    </form>

    <div class="card pa-card">
        @if ($users->isEmpty())
            <x-admin.empty-state icon="bi-people" title="No users found" message="Try a different search or filter." />
        @else
            <div class="table-responsive">
                <table class="table pa-table align-middle mb-0">
                    <caption class="visually-hidden">Users</caption>
                    <thead>
                        <tr>
                            <th scope="col">Name</th>
                            <th scope="col">Roles</th>
                            <th scope="col">Status</th>
                            <th scope="col">2FA</th>
                            <th scope="col">Last sign-in</th>
                            <th scope="col"><span class="visually-hidden">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $user)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $user->name }}</div>
                                    <div class="small text-body-secondary">{{ $user->email }}</div>
                                </td>
                                <td>{{ $user->roles->map(fn ($r) => \App\Auth\PermissionCatalog::roleLabel($r->name))->join(', ') ?: '—' }}</td>
                                <td><x-admin.status-badge :status="$user->status" /></td>
                                <td>
                                    @if ($user->two_factor_confirmed_at)
                                        <i class="bi bi-shield-check text-success" aria-hidden="true"></i><span class="visually-hidden">Enabled</span>
                                    @else
                                        <span class="text-body-secondary">—<span class="visually-hidden">Not enabled</span></span>
                                    @endif
                                </td>
                                <td class="small">{{ $user->last_login_at?->diffForHumans() ?? 'Never' }}</td>
                                <td class="text-end text-nowrap">
                                    @can('update', $user)
                                        <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-sm btn-outline-secondary">Edit<span class="visually-hidden"> {{ $user->name }}</span></a>
                                    @endcan
                                    @can('delete', $user)
                                        <form method="POST" action="{{ route('admin.users.destroy', $user) }}" class="d-inline"
                                              data-confirm="Delete {{ $user->name }}? They will no longer be able to sign in.">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Delete<span class="visually-hidden"> {{ $user->name }}</span></button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer">{{ $users->links() }}</div>
        @endif
    </div>
</x-admin.layout>
