<x-admin.layout title="Messages">
    <x-admin.page-header title="Messages" :subtitle="'Sent with Contact form blocks. Each message is also e-mailed, and deleted from here after '.\App\Models\ContactMessage::RETENTION_DAYS.' days.'" />

    <nav class="pa-tabs mb-3" aria-label="Show">
        <a href="{{ route('admin.messages.index') }}" @class(['btn btn-sm', 'btn-secondary' => $filter === 'all', 'btn-outline-secondary' => $filter !== 'all']) @if ($filter === 'all') aria-current="page" @endif>All</a>
        <a href="{{ route('admin.messages.index', ['show' => 'unread']) }}" @class(['btn btn-sm', 'btn-secondary' => $filter === 'unread', 'btn-outline-secondary' => $filter !== 'unread']) @if ($filter === 'unread') aria-current="page" @endif>Unread ({{ $unread }})</a>
    </nav>

    <div class="card pa-card">
        @if ($messages->isEmpty())
            <x-admin.empty-state icon="bi-envelope" title="No messages" />
        @else
            <div class="table-responsive">
                <table class="table pa-table align-middle mb-0">
                    <caption class="visually-hidden">Messages</caption>
                    <thead><tr><th scope="col">From</th><th scope="col">Subject</th><th scope="col">Received</th><th scope="col"><span class="visually-hidden">Actions</span></th></tr></thead>
                    <tbody>
                        @foreach ($messages as $message)
                            <tr @class(['fw-semibold' => $message->read_at === null])>
                                <td>
                                    @if ($message->read_at === null)<span class="pa-badge pa-badge--warning me-1">New</span>@endif
                                    {{ $message->name }}
                                    <div class="small text-body-secondary fw-normal">{{ $message->email }}</div>
                                </td>
                                <td class="small">{{ $message->subject ?: \Illuminate\Support\Str::limit($message->message, 70) }}</td>
                                <td class="small">{{ $message->created_at?->diffForHumans() }}</td>
                                <td class="text-end"><a href="{{ route('admin.messages.show', $message) }}" class="btn btn-sm btn-outline-secondary">Open<span class="visually-hidden"> message from {{ $message->name }}</span></a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
    <div class="mt-3">{{ $messages->links() }}</div>
</x-admin.layout>
