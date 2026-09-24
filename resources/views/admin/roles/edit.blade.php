@php($label = \App\Auth\PermissionCatalog::roleLabel($role->name))
<x-admin.layout :title="'Edit '.$label">
    <x-admin.page-header :title="'Permissions: '.$label" subtitle="Tick the actions this role may perform." />

    <form method="POST" action="{{ route('admin.roles.update', $role) }}">
        @csrf @method('PUT')
        <div class="row g-4">
            @foreach ($groups as $group => $permissions)
                <div class="col-md-6 col-xl-4">
                    <fieldset class="card pa-card h-100">
                        <legend class="card-header h6 mb-0 w-100 float-none fs-6">{{ $group }}</legend>
                        <div class="card-body">
                            @foreach ($permissions as $permission)
                                @php($id = 'perm-'.str_replace('.', '-', $permission))
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="permissions[]" value="{{ $permission }}" id="{{ $id }}" @checked(in_array($permission, old('permissions', $granted), true))>
                                    <label class="form-check-label" for="{{ $id }}"><code>{{ $permission }}</code></label>
                                </div>
                            @endforeach
                        </div>
                    </fieldset>
                </div>
            @endforeach
        </div>
        <div class="d-flex gap-2 mt-4">
            <button type="submit" class="btn btn-primary">Save permissions</button>
            <a href="{{ route('admin.roles.index') }}" class="btn btn-link">Cancel</a>
        </div>
    </form>
</x-admin.layout>
