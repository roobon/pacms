<x-admin.layout title="Header & footer">
    <x-admin.page-header title="Header &amp; footer" subtitle="The header and footer every page shows (a page can choose another one, or none), the logo and your social profiles." />

    <form method="POST" action="{{ route('admin.settings.navigation.update') }}" novalidate>
        @csrf @method('PUT')
        <div class="row g-4">
            <div class="col-lg-8">
                <section class="card pa-card mb-4" aria-labelledby="chrome-heading">
                    <div class="card-header"><h2 id="chrome-heading" class="h6 mb-0">Header and footer</h2></div>
                    <div class="card-body">
                        @foreach (['header_global_block_id' => [$headers, 'Header', 'header'], 'footer_global_block_id' => [$footers, 'Footer', 'footer']] as $name => [$choices, $label, $kind])
                            <div class="mb-3">
                                <label for="field-{{ $name }}" class="form-label">{{ $label }}</label>
                                <select id="field-{{ $name }}" name="{{ $name }}" class="form-select @error($name) is-invalid @enderror" aria-describedby="field-{{ $name }}-help">
                                    <option value="">None (a plain {{ strtolower($label) }} with the site name)</option>
                                    @foreach ($choices as $choice)
                                        <option value="{{ $choice->id }}" @selected((int) old($name, $navigation[$name]) === $choice->id)>{{ $choice->name }}{{ $choice->published_revision_id ? '' : ' (not published yet)' }}</option>
                                    @endforeach
                                </select>
                                @error($name)<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <div id="field-{{ $name }}-help" class="form-text">
                                    {{ $label }}s are global blocks of kind “{{ $label }}”, built with blocks such as Site logo, Menu, Social links and Copyright.
                                    <a href="{{ route('admin.global-blocks.index') }}">Global blocks</a>
                                </div>
                            </div>
                        @endforeach
                        <div class="form-check mb-2">
                            <input type="hidden" name="sticky_header" value="0">
                            <input class="form-check-input" type="checkbox" name="sticky_header" value="1" id="field-sticky" @checked(old('sticky_header', $navigation['sticky_header']))>
                            <label class="form-check-label" for="field-sticky">Keep the header at the top while scrolling</label>
                        </div>
                        <div class="form-check">
                            <input type="hidden" name="transparent_header" value="0">
                            <input class="form-check-input" type="checkbox" name="transparent_header" value="1" id="field-transparent" aria-describedby="field-transparent-help" @checked(old('transparent_header', $navigation['transparent_header']))>
                            <label class="form-check-label" for="field-transparent">See-through header over a hero or slider</label>
                            <div id="field-transparent-help" class="form-text mt-0">On pages that start with a hero or slider, the header sits on top of it with white text, and gets its background back when you scroll.</div>
                        </div>
                    </div>
                </section>

                <section class="card pa-card mb-4" aria-labelledby="social-heading">
                    <div class="card-header"><h2 id="social-heading" class="h6 mb-0">Social profiles</h2></div>
                    <div class="card-body">
                        <p class="small text-body-secondary">Shown by Social links blocks. Leave empty the ones you do not use.</p>
                        @foreach ($networks as $network => [$label, $icon])
                            <div class="mb-3">
                                <label for="field-social-{{ $network }}" class="form-label"><i class="bi {{ $icon }} me-1" aria-hidden="true"></i>{{ $label }}</label>
                                <input id="field-social-{{ $network }}" type="url" name="social[{{ $network }}]" class="form-control @error("social.{$network}") is-invalid @enderror" value="{{ old("social.{$network}", $navigation['social'][$network] ?? '') }}" placeholder="https://…" maxlength="500">
                                @error("social.{$network}")<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        @endforeach
                    </div>
                </section>

                <section class="card pa-card" aria-labelledby="copyright-heading">
                    <div class="card-header"><h2 id="copyright-heading" class="h6 mb-0">Copyright line</h2></div>
                    <div class="card-body">
                        @php($placeholders = ['{'.'{year}}', '{'.'{site_name}}'])
                        <x-admin.field name="copyright" label="Text" :value="$navigation['copyright']" :help="'Shown by Copyright blocks. '.$placeholders[0].' becomes the current year and '.$placeholders[1].' your site name.'" />
                    </div>
                </section>
            </div>

            <div class="col-lg-4">
                <section class="card pa-card mb-4" aria-labelledby="logo-heading">
                    <div class="card-header"><h2 id="logo-heading" class="h6 mb-0">Logo</h2></div>
                    <div class="card-body">
                        <x-admin.media-picker name="logo_media_id" label="Logo" :media="$logo" help="PNG or WebP with a transparent background works best." />
                        <x-admin.media-picker name="logo_dark_media_id" label="Logo for dark backgrounds" :media="$logoDark" help="Optional: a light version for dark headers and footers." />
                    </div>
                </section>
                <div>
                    <button type="submit" class="btn btn-primary w-100">Save</button>
                </div>
            </div>
        </div>
    </form>
</x-admin.layout>
