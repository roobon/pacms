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
            </div>
        </div>
        <button type="submit" class="btn btn-primary mt-4">Save settings</button>
    </form>
</x-admin.layout>
