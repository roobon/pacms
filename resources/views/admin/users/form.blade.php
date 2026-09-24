@php($editing = $user->exists)
<x-admin.layout :title="$editing ? 'Edit user' : 'Add user'">
    <x-admin.page-header :title="$editing ? 'Edit '.$user->name : 'Add user'" />

    <form method="POST" action="{{ $editing ? route('admin.users.update', $user) : route('admin.users.store') }}" novalidate>
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card pa-card">
                    <div class="card-body">
                        <x-admin.field name="name" label="Name" :value="$user->name" required autocomplete="name" />
                        <x-admin.field name="email" label="E-mail" type="email" :value="$user->email" required autocomplete="email" />
                        <x-admin.field name="password" label="{{ $editing ? 'New password' : 'Password' }}" type="password"
                            :required="! $editing" autocomplete="new-password"
                            help="{{ $editing ? 'Leave empty to keep the current password. ' : '' }}At least {{ config('pacms.security.password_min_length') }} characters with letters and numbers." />
                        <x-admin.field name="password_confirmation" label="Confirm password" type="password" :required="! $editing" autocomplete="new-password" />
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card pa-card mb-4">
                    <div class="card-body">
                        <fieldset class="mb-3">
                            <legend class="form-label">Status</legend>
                            @foreach (\App\Enums\UserStatus::cases() as $status)
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="status" id="status-{{ $status->value }}" value="{{ $status->value }}"
                                        @checked(old('status', $user->status?->value) === $status->value)>
                                    <label class="form-check-label" for="status-{{ $status->value }}">{{ $status->label() }}</label>
                                </div>
                            @endforeach
                        </fieldset>

                        <fieldset>
                            <legend class="form-label">Roles</legend>
                            @php($current = old('roles', $user->exists ? $user->roles->pluck('name')->all() : []))
                            @forelse ($assignableRoles as $role)
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="roles[]" id="role-{{ $role->name }}" value="{{ $role->name }}" @checked(in_array($role->name, $current, true))>
                                    <label class="form-check-label" for="role-{{ $role->name }}">{{ \App\Auth\PermissionCatalog::roleLabel($role->name) }}</label>
                                </div>
                            @empty
                                <p class="small text-body-secondary mb-0">You cannot assign any roles.</p>
                            @endforelse
                            <p class="form-text">You can only assign roles whose permissions you hold yourself.</p>
                        </fieldset>
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">{{ $editing ? 'Save changes' : 'Create user' }}</button>
                    <a href="{{ route('admin.users.index') }}" class="btn btn-link">Cancel</a>
                </div>
            </div>
        </div>
    </form>
</x-admin.layout>
