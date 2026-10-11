<x-admin.layout title="Connect a Facebook Page">
    <x-admin.page-header title="Connect a Facebook Page" subtitle="Show the posts of your organisation's own Page with a “Facebook posts” block. PACMS reads them through Facebook's official API about once an hour and keeps copies of the images, so visitors never load anything from Facebook.">
        <x-slot:actions><a href="{{ route('admin.external-sources.index') }}">All sources</a></x-slot:actions>
    </x-admin.page-header>

    <div class="row g-4">
        <div class="col-lg-7">
            @if ($pages !== [])
                <section class="card pa-card mb-4" aria-labelledby="choose-heading">
                    <div class="card-header"><h2 id="choose-heading" class="h6 mb-0">Choose the Page</h2></div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('admin.facebook.choose') }}">
                            @csrf
                            @foreach ($pages as $page)
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="radio" name="page_id" value="{{ $page['id'] }}" id="page-{{ $page['id'] }}" @checked($loop->first)>
                                    <label class="form-check-label" for="page-{{ $page['id'] }}">{{ $page['name'] }} <span class="small text-body-secondary">({{ $page['id'] }})</span></label>
                                </div>
                            @endforeach
                            <button type="submit" class="btn btn-primary mt-2">Connect this Page</button>
                        </form>
                    </div>
                </section>
            @endif

            <section class="card pa-card mb-4" aria-labelledby="oauth-heading">
                <div class="card-header"><h2 id="oauth-heading" class="h6 mb-0">1. Connect with Facebook</h2></div>
                <div class="card-body">
                    @if ($appConfigured)
                        <p class="small">Sign in with a Facebook account that manages the Page and allow PACMS to read it. PACMS keeps only the Page's access token, encrypted.</p>
                        <a href="{{ route('admin.facebook.start') }}" class="btn btn-primary"><i class="bi bi-facebook me-1" aria-hidden="true"></i> Connect with Facebook</a>
                        <p class="small text-body-secondary mt-3 mb-0">In your Meta app, add this address as a valid OAuth redirect: <code>{{ $callback }}</code></p>
                    @else
                        <p class="small mb-0">Needs a Meta app: set <code>META_APP_ID</code> and <code>META_APP_SECRET</code> in the server's <code>.env</code>. Or use option 2.</p>
                    @endif
                </div>
            </section>

            <section class="card pa-card" aria-labelledby="manual-heading">
                <div class="card-header"><h2 id="manual-heading" class="h6 mb-0">2. Paste a Page access token</h2></div>
                <div class="card-body">
                    <p class="small">Recommended for organisations with Meta Business Manager: a <strong>system user</strong> token with the permissions <code>pages_read_engagement</code> and <code>pages_read_user_content</code> does not expire.</p>
                    <form method="POST" action="{{ route('admin.facebook.manual') }}" novalidate>
                        @csrf
                        <x-admin.field name="page_id" label="Page ID" required help="A number: on Facebook, the Page's About → Page transparency, or Page settings." />
                        <x-admin.field name="page_token" label="Page access token" type="password" required autocomplete="off" help="Stored encrypted and never shown again." />
                        <button type="submit" class="btn btn-primary">Check and connect</button>
                    </form>
                </div>
            </section>
        </div>
        <aside class="col-lg-5">
            <section class="card pa-card" aria-labelledby="about-heading">
                <div class="card-header"><h2 id="about-heading" class="h6 mb-0">Good to know</h2></div>
                <div class="card-body small">
                    <ul class="mb-0 ps-3">
                        <li class="mb-2">Only your organisation's <strong>own</strong> Page can be shown. Other Pages need Facebook's separate review and are not supported.</li>
                        <li class="mb-2">Only published posts are shown, newest first.</li>
                        <li class="mb-2">Post images are saved in the Media Library (category “Facebook cache”) and removed when their posts leave the list.</li>
                        <li class="mb-2">If the token stops working, the stored posts keep showing and the source shows the error; connect again with a new token.</li>
                        <li>No Facebook scripts or cookies are loaded for visitors.</li>
                    </ul>
                </div>
            </section>
        </aside>
    </div>
</x-admin.layout>
