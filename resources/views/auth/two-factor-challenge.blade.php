<x-auth.layout title="Two-factor authentication">
    <p>Enter the 6-digit code from your authenticator app. If you have lost access to it, use one of your recovery codes instead.</p>
    <form method="POST" action="{{ route('two-factor.login.store') }}" novalidate>
        @csrf
        <x-admin.field name="code" label="Authentication code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" autofocus />
        <details class="mb-3" @if ($errors->has('recovery_code')) open @endif>
            <summary class="small">Use a recovery code</summary>
            <div class="mt-2">
                <x-admin.field name="recovery_code" label="Recovery code" autocomplete="off" />
            </div>
        </details>
        <button type="submit" class="btn btn-primary w-100">Verify</button>
    </form>
</x-auth.layout>
