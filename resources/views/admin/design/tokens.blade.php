<x-admin.layout title="Design Tokens">
    <x-admin.page-header title="Design Tokens" subtitle="The organisation's visual identity. Changes apply to the public website immediately — no rebuild needed." />

    @php($warnings = session('token_warnings', $warnings))
    @if ($warnings)
        <div class="alert alert-warning pa-alert" role="alert">
            <strong><i class="bi bi-universal-access" aria-hidden="true"></i> Accessibility warnings</strong>
            <ul class="mb-0 ps-3">
                @foreach ($warnings as $warning)
                    <li>{{ $warning }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row g-4">
        <div class="col-xl-5">
            <form method="POST" action="{{ route('admin.design.tokens.update') }}">
                @csrf @method('PUT')
                @foreach ($definitions as $group => $tokens)
                    <fieldset class="card pa-card mb-4">
                        <legend class="card-header h6 mb-0 w-100 float-none fs-6">{{ $group }}</legend>
                        <div class="card-body">
                            @foreach ($tokens as $token => $definition)
                                @php($id = 'token-'.str_replace('.', '-', $token))
                                @php($value = old('tokens.'.$token, $values[$token]))
                                <div class="row align-items-center mb-2 g-2">
                                    <label for="{{ $id }}" class="col-6 col-form-label col-form-label-sm">
                                        {{ $definition['label'] }}
                                        <code class="d-block small text-body-secondary">{{ $token }}</code>
                                    </label>
                                    <div class="col-6">
                                        @if ($definition['type'] === 'color')
                                            <div class="input-group input-group-sm">
                                                <input type="color" class="form-control form-control-color" value="{{ $value }}" aria-label="{{ $definition['label'] }} colour picker" data-sync="#{{ $id }}">
                                                <input type="text" id="{{ $id }}" name="tokens[{{ $token }}]" value="{{ $value }}" class="form-control font-monospace @error('tokens.'.$token) is-invalid @enderror" pattern="#[0-9A-Fa-f]{6}" maxlength="7" data-sync-target>
                                            </div>
                                        @elseif ($definition['type'] === 'font')
                                            <select id="{{ $id }}" name="tokens[{{ $token }}]" class="form-select form-select-sm">
                                                @foreach ($fonts as $key => $font)
                                                    <option value="{{ $key }}" @selected($value === $key)>{{ $font['label'] }}</option>
                                                @endforeach
                                            </select>
                                        @else
                                            <input type="text" id="{{ $id }}" name="tokens[{{ $token }}]" value="{{ $value }}" class="form-control form-control-sm font-monospace @error('tokens.'.$token) is-invalid @enderror" aria-describedby="{{ $id }}-help">
                                            <div id="{{ $id }}-help" class="visually-hidden">A number followed by px or rem, for example 0.5rem.</div>
                                        @endif
                                        @error('tokens.'.$token)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </fieldset>
                @endforeach
                <button type="submit" class="btn btn-primary">Save &amp; publish tokens</button>
            </form>
        </div>
        <div class="col-xl-7">
            <section class="card pa-card pa-sticky" aria-labelledby="preview-heading">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h2 id="preview-heading" class="h6 mb-0">Component preview (saved tokens)</h2>
                    <a href="{{ route('admin.design.preview') }}" target="_blank" rel="noopener" class="small">Open full page<span class="visually-hidden"> (opens in new tab)</span></a>
                </div>
                <iframe src="{{ route('admin.design.preview') }}" title="Design system preview" class="pa-preview-frame" loading="lazy"></iframe>
            </section>
        </div>
    </div>
</x-admin.layout>
