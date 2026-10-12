<x-admin.layout :title="'Message from '.$message->name">
    <x-admin.page-header :title="$message->subject ?: 'Message from '.$message->name">
        <x-slot:actions><a href="{{ route('admin.messages.index') }}">All messages</a></x-slot:actions>
    </x-admin.page-header>

    <div class="row g-4">
        <div class="col-lg-8">
            <section class="card pa-card" aria-label="Message">
                <div class="card-body">
                    <p class="pa-message-text mb-0" style="white-space: pre-line">{{ $message->message }}</p>
                </div>
            </section>
        </div>
        <aside class="col-lg-4">
            <section class="card pa-card mb-4" aria-labelledby="from-heading">
                <div class="card-header"><h2 id="from-heading" class="h6 mb-0">From</h2></div>
                <div class="card-body small">
                    <p class="mb-1 fw-semibold">{{ $message->name }}</p>
                    <p class="mb-3"><a href="mailto:{{ $message->email }}?subject={{ rawurlencode('Re: '.($message->subject ?: 'your message')) }}">{{ $message->email }}</a></p>
                    <dl class="mb-0">
                        <dt>Received</dt><dd>{{ $message->created_at?->format('j F Y, H:i') }}</dd>
                        @if ($message->page_url)<dt>Page</dt><dd><a href="{{ url($message->page_url) }}" target="_blank" rel="noopener noreferrer">{{ $message->page_url }}</a></dd>@endif
                        <dt>Sent to</dt><dd>{{ $message->recipient ?: '—' }}{{ $message->mailed ? '' : ' (not e-mailed: no address)' }}</dd>
                        @if ($message->consent)<dt>Consent</dt><dd>Given</dd>@endif
                        <dt>Deleted on</dt><dd>{{ $message->created_at?->addDays(\App\Models\ContactMessage::RETENTION_DAYS)->format('j F Y') }}</dd>
                    </dl>
                </div>
            </section>
            <a href="mailto:{{ $message->email }}?subject={{ rawurlencode('Re: '.($message->subject ?: 'your message')) }}" class="btn btn-primary w-100 mb-2"><i class="bi bi-reply" aria-hidden="true"></i> Reply by e-mail</a>
            <form method="POST" action="{{ route('admin.messages.unread', $message) }}" class="mb-2">
                @csrf
                <button type="submit" class="btn btn-outline-secondary w-100">Mark as unread</button>
            </form>
            <form method="POST" action="{{ route('admin.messages.destroy', $message) }}" data-confirm="Delete this message?">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-outline-danger w-100">Delete</button>
            </form>
        </aside>
    </div>
</x-admin.layout>
