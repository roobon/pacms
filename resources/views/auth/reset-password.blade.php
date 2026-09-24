<x-auth.layout title="Choose a new password">
    <form method="POST" action="{{ route('password.update') }}" novalidate>
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <x-admin.field name="email" label="E-mail" type="email" :value="$request->query('email')" required autocomplete="username" />
        <x-admin.field name="password" label="New password" type="password" required autocomplete="new-password"
            help="At least {{ config('pacms.security.password_min_length') }} characters with letters and numbers." />
        <x-admin.field name="password_confirmation" label="Confirm new password" type="password" required autocomplete="new-password" />
        <button type="submit" class="btn btn-primary w-100">Reset password</button>
    </form>
</x-auth.layout>
