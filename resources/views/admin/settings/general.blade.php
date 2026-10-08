<x-admin.layout title="General settings">
    <x-admin.page-header title="General settings" subtitle="Organisation details shown on the public website." />

    <form method="POST" action="{{ route('admin.settings.general.update') }}" novalidate>
        @csrf @method('PUT')
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card pa-card">
                    <div class="card-body">
                        <x-admin.field name="name" label="Site / organisation name" :value="$site['name']" required />
                        <x-admin.field name="tagline" label="Tagline" :value="$site['tagline']" />
                        <x-admin.field name="description" label="Short description" type="textarea" :value="$site['description']"
                            help="Used as the default meta description for search engines and link previews." />
                        <x-admin.field name="contact_email" label="Contact e-mail" type="email" :value="$site['contact_email']" />
                        <x-admin.field name="contact_phone" label="Contact phone" :value="$site['contact_phone']" />
                        <x-admin.field name="address" label="Address" type="textarea" :value="$site['address']" />
                        <div class="mb-3">
                            <label for="field-timezone" class="form-label">Time zone <span class="pa-required">(required)</span></label>
                            <select id="field-timezone" name="timezone" class="form-select @error('timezone') is-invalid @enderror">
                                @foreach ($timezones as $tz)
                                    <option value="{{ $tz }}" @selected(old('timezone', $site['timezone']) === $tz)>{{ $tz }}</option>
                                @endforeach
                            </select>
                            @error('timezone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>

                <section class="card pa-card mt-4" aria-labelledby="homepage-heading">
                    <div class="card-header"><h2 id="homepage-heading" class="h6 mb-0">Homepage &amp; search engines</h2></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="field-homepage" class="form-label">Homepage</label>
                            <select id="field-homepage" name="homepage_page_id" class="form-select @error('homepage_page_id') is-invalid @enderror" aria-describedby="homepage-help">
                                <option value="">— Default welcome page —</option>
                                @foreach ($livePages as $livePage)
                                    <option value="{{ $livePage->id }}" @selected((int) old('homepage_page_id', $site['homepage_page_id']) === $livePage->id)>{{ $livePage->title }} (/{{ $livePage->published_path }})</option>
                                @endforeach
                            </select>
                            <div id="homepage-help" class="form-text">Only published pages can be the homepage. Its own address then redirects to <code>/</code>.</div>
                            @error('homepage_page_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <x-admin.field name="robots_txt" label="robots.txt rules" type="textarea" :value="$seo['robots_txt']"
                            help="The sitemap line is added automatically. Changes apply immediately." class="font-monospace" rows="5" />
                    </div>
                </section>

                <section class="card pa-card mt-4" aria-labelledby="media-settings-heading">
                    <div class="card-header"><h2 id="media-settings-heading" class="h6 mb-0">Media</h2></div>
                    <div class="card-body">
                        <div class="form-check">
                            <input type="hidden" name="allow_svg" value="0">
                            <input class="form-check-input" type="checkbox" name="allow_svg" value="1" id="allow-svg" aria-describedby="allow-svg-help" @checked(old('allow_svg', $media['allow_svg']))>
                            <label class="form-check-label" for="allow-svg">Allow SVG uploads (logos and icons)</label>
                        </div>
                        <div id="allow-svg-help" class="form-text">Only for roles with the “Upload SVG” permission (Administrator by default). Every SVG is cleaned: scripts, event handlers and external references are removed. Off by default because SVG files can contain code.</div>
                    </div>
                </section>

                <section class="card pa-card mt-4" aria-labelledby="sidebars-heading">
                    <div class="card-header"><h2 id="sidebars-heading" class="h6 mb-0">Sidebars</h2></div>
                    <div class="card-body">
                        <p class="small text-body-secondary">
                            The sidebar shown next to each {{ strtolower(collect($contentTypes)->map->singular()->join(', ', ' and ')) }} page. Each item can still choose its own sidebar or none.
                            Any published global block can be a sidebar; blocks set to “Used as: Sidebar” are listed first.
                            <strong>Position</strong> (left or right) applies to every item of that module, including items that choose their own sidebar.
                        </p>
                        @foreach ($contentTypes as $key => $type)
                            @php($current = (array) ($sidebars[$key] ?? []))
                            <fieldset class="pa-settings-row mb-3">
                                <legend class="form-label fs-6 fw-semibold">{{ $type->label() }}</legend>
                                <div class="row g-2">
                                    <div class="col-sm-8">
                                        <label for="sidebar-{{ $key }}" class="form-label small">Sidebar</label>
                                        <select id="sidebar-{{ $key }}" name="sidebars[{{ $key }}][global_block_id]" class="form-select @error("sidebars.$key.global_block_id") is-invalid @enderror">
                                            <option value="">None</option>
                                            <x-admin.sidebar-options :blocks="$sidebarBlocks" :selected="old('sidebars.'.$key.'.global_block_id', $current['global_block_id'] ?? null)" />
                                        </select>
                                        @error("sidebars.$key.global_block_id")<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-sm-4">
                                        <label for="sidebar-{{ $key }}-position" class="form-label small">Position</label>
                                        <select id="sidebar-{{ $key }}-position" name="sidebars[{{ $key }}][position]" class="form-select">
                                            @foreach (['right' => 'Right', 'left' => 'Left'] as $value => $label)
                                                <option value="{{ $value }}" @selected(old("sidebars.$key.position", $current['position'] ?? 'right') === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </fieldset>
                        @endforeach
                        @if ($sidebarBlocks->isEmpty())
                            <p class="small mb-0">No published global blocks yet.@can('global_blocks.manage') <a href="{{ route('admin.global-blocks.create') }}">Create a global block</a> (set “Used as” to “Sidebar”), then publish it.@endcan</p>
                        @endif
                    </div>
                </section>
            </div>
        </div>
        <button type="submit" class="btn btn-primary mt-4">Save settings</button>
    </form>
</x-admin.layout>
