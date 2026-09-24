<x-admin.layout title="Profile">
    <x-admin.page-header title="Your profile" />

    @if (session('status') === 'profile-information-updated')
        <div class="alert alert-success pa-alert" role="status">Profile updated.</div>
    @elseif (session('status') === 'password-updated')
        <div class="alert alert-success pa-alert" role="status">Password changed.</div>
    @endif

    <div class="row g-4">
        <div class="col-lg-6">
            <section class="card pa-card" aria-labelledby="profile-heading">
                <div class="card-header"><h2 id="profile-heading" class="h6 mb-0">Profile information</h2></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('user-profile-information.update') }}" novalidate>
                        @csrf @method('PUT')
                        @php($bag = $errors->getBag('updateProfileInformation'))
                        <div class="mb-3">
                            <label for="profile-name" class="form-label">Name</label>
                            <input id="profile-name" name="name" class="form-control @if ($bag->has('name')) is-invalid @endif" value="{{ old('name', $user->name) }}" required autocomplete="name">
                            @if ($bag->has('name'))<div class="invalid-feedback">{{ $bag->first('name') }}</div>@endif
                        </div>
                        <div class="mb-3">
                            <label for="profile-email" class="form-label">E-mail</label>
                            <input id="profile-email" name="email" type="email" class="form-control @if ($bag->has('email')) is-invalid @endif" value="{{ old('email', $user->email) }}" required autocomplete="email" aria-describedby="profile-email-help">
                            <div id="profile-email-help" class="form-text">Changing your e-mail requires verifying the new address before you can use the admin again.</div>
                            @if ($bag->has('email'))<div class="invalid-feedback">{{ $bag->first('email') }}</div>@endif
                        </div>
                        <button type="submit" class="btn btn-primary">Save profile</button>
                    </form>
                </div>
            </section>
        </div>

        <div class="col-lg-6">
            <section class="card pa-card" aria-labelledby="password-heading">
                <div class="card-header"><h2 id="password-heading" class="h6 mb-0">Change password</h2></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('user-password.update') }}" novalidate>
                        @csrf @method('PUT')
                        @php($bag = $errors->getBag('updatePassword'))
                        @foreach (['current_password' => ['Current password', 'current-password'], 'password' => ['New password', 'new-password'], 'password_confirmation' => ['Confirm new password', 'new-password']] as $field => [$label, $autocomplete])
                            <div class="mb-3">
                                <label for="pw-{{ $field }}" class="form-label">{{ $label }}</label>
                                <input id="pw-{{ $field }}" name="{{ $field }}" type="password" class="form-control @if ($bag->has($field)) is-invalid @endif" required autocomplete="{{ $autocomplete }}">
                                @if ($bag->has($field))<div class="invalid-feedback">{{ $bag->first($field) }}</div>@endif
                            </div>
                        @endforeach
                        <button type="submit" class="btn btn-primary">Change password</button>
                    </form>
                </div>
            </section>
        </div>
    </div>
</x-admin.layout>
