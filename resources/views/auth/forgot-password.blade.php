<x-auth.layout title="Reset your password">
    <p>Enter your e-mail address and we will send you a link to choose a new password.</p>
    <form method="POST" action="{{ route('password.email') }}" novalidate>
        @csrf
        <x-admin.field name="email" label="E-mail" type="email" required autocomplete="email" autofocus />
        <button type="submit" class="btn btn-primary w-100">Send reset link</button>
    </form>
    <p class="text-center mt-3 mb-0"><a href="{{ route('login') }}" class="small">Back to sign in</a></p>
</x-auth.layout>
