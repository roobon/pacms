@php
    use App\Enums\TestimonialStatus;
    $tabs = [
        'queue' => 'To moderate',
        'draft' => 'Drafts',
        'approved' => 'Approved',
        'published' => 'Published',
        'rejected' => 'Rejected',
        'archived' => 'Archived',
        'all' => 'All',
    ];
@endphp
<x-admin.layout title="Testimonials">
    <x-admin.page-header title="Testimonials" subtitle="Submitted by registered users or entered here. Published testimonials appear in Testimonials blocks on pages.">
        @can('create', App\Models\Testimonial::class)
            <x-slot:actions>
                <a href="{{ route('admin.testimonials.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg" aria-hidden="true"></i> New testimonial</a>
            </x-slot:actions>
        @endcan
    </x-admin.page-header>

    <nav aria-label="Testimonial lists" class="mb-3">
        <ul class="nav nav-pills flex-wrap gap-1">
            @foreach ($tabs as $key => $label)
                <li class="nav-item">
                    <a href="{{ route('admin.testimonials.index', array_filter(['view' => $key, 'q' => $filters['q'] ?? null])) }}"
                       @class(['nav-link', 'active' => $view === $key]) @if ($view === $key) aria-current="page" @endif>
                        {{ $label }} <span class="pa-badge pa-badge--neutral ms-1">{{ $counts[$key] ?? 0 }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>

    <form method="GET" class="pa-filters" role="search" aria-label="Search testimonials">
        <input type="hidden" name="view" value="{{ $view }}">
        <div><label for="filter-q" class="form-label">Search</label><input id="filter-q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" placeholder="Name, organisation or text"></div>
        <div class="pa-filters__actions"><button class="btn btn-secondary" type="submit">Search</button></div>
    </form>

    <div class="card pa-card">
        @if ($items->isEmpty())
            <x-admin.empty-state icon="bi-chat-quote" :title="$view === 'queue' ? 'Nothing waiting for moderation' : 'No testimonials here'" />
        @else
            <div class="table-responsive">
                <table class="table pa-table align-middle mb-0">
                    <caption class="visually-hidden">Testimonials</caption>
                    <thead><tr><th scope="col">From</th><th scope="col">Testimonial</th><th scope="col">Status</th><th scope="col">Received</th><th scope="col"><span class="visually-hidden">Actions</span></th></tr></thead>
                    <tbody>
                        @foreach ($items as $item)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.testimonials.edit', $item) }}" class="fw-semibold">{{ $item->name }}</a>
                                    @if ($item->featured)<span class="pa-badge pa-badge--info ms-1">Featured</span>@endif
                                    <div class="small text-body-secondary">{{ collect([$item->designation, $item->organization])->filter()->implode(', ') ?: '—' }}</div>
                                </td>
                                <td class="small">{{ Str::limit($item->body, 140) }}</td>
                                <td>
                                    <span class="pa-badge pa-badge--{{ $item->status->badge() }}">{{ $item->status->label() }}</span>
                                    <div class="small text-body-secondary">{{ $item->user_id ? 'Submitted online' : 'Entered by staff' }}</div>
                                </td>
                                <td class="small">{{ $item->created_at?->diffForHumans() }}</td>
                                <td class="text-end"><a href="{{ route('admin.testimonials.edit', $item) }}" class="btn btn-sm btn-outline-secondary">{{ in_array($item->status, TestimonialStatus::awaitingModeration(), true) ? 'Review' : 'Open' }}<span class="visually-hidden"> testimonial from {{ $item->name }}</span></a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer">{{ $items->links() }}</div>
        @endif
    </div>
</x-admin.layout>
