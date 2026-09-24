<x-auth.layout title="Verify your e-mail address">
    @if (session('status') === 'verification-link-sent')
        <div class="alert alert-success pa-alert" role="status">A new verification link has been sent to your e-mail address.</div>
    @endif
    <p>Please confirm your e-mail address by clicking the link we sent you. Didn't receive it?</p>
    <form method="POST" action="{{ route('verification.send') }}" class="mb-3">
        @csrf
        <button type="submit" class="btn btn-primary w-100">Resend verification e-mail</button>
    </form>
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="btn btn-link w-100">Sign out</button>
    </form>
</x-auth.layout>
