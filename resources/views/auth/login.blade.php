<x-auth.layout title="Sign in">
    <form method="POST" action="{{ route('login') }}" novalidate>
        @csrf
        <x-admin.field name="email" label="E-mail" type="email" required autocomplete="username" autofocus />
        <x-admin.field name="password" label="Password" type="password" required autocomplete="current-password" />
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="remember" id="remember" value="1">
                <label class="form-check-label" for="remember">Keep me signed in</label>
            </div>
            <a href="{{ route('password.request') }}" class="small">Forgot password?</a>
        </div>
        <button type="submit" class="btn btn-primary w-100">Sign in</button>
    </form>
</x-auth.layout>
