<x-auth.layout title="Confirm your password">
    <p>This is a secure area. Please confirm your password before continuing.</p>
    <form method="POST" action="{{ route('password.confirm') }}" novalidate>
        @csrf
        <x-admin.field name="password" label="Password" type="password" required autocomplete="current-password" autofocus />
        <button type="submit" class="btn btn-primary w-100">Confirm</button>
    </form>
</x-auth.layout>
