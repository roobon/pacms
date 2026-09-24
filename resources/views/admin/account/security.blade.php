<x-admin.layout title="Security">
    <x-admin.page-header title="Account security" subtitle="Two-factor authentication protects your account even if your password is stolen." />

    <div class="row g-4">
        <div class="col-lg-8">
            <section class="card pa-card" aria-labelledby="tfa-heading">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h2 id="tfa-heading" class="h6 mb-0">Two-factor authentication</h2>
                    @if ($twoFactorConfirmed)
                        <span class="pa-badge pa-badge--success">Enabled</span>
                    @else
                        <span class="pa-badge pa-badge--neutral">Not enabled</span>
                    @endif
                </div>
                <div class="card-body">
                    @if ($user->requiresTwoFactor() && ! $twoFactorConfirmed)
                        <p class="alert alert-warning pa-alert">Your role requires two-factor authentication before you can use the admin.</p>
                    @endif

                    @if (! $twoFactorEnabled)
                        <p>Use an authenticator app (such as Google Authenticator, Microsoft Authenticator or 1Password) to generate sign-in codes.</p>
                        <form method="POST" action="{{ route('two-factor.enable') }}">
                            @csrf
                            <button type="submit" class="btn btn-primary"><i class="bi bi-shield-plus" aria-hidden="true"></i> Enable two-factor authentication</button>
                        </form>
                    @elseif (! $twoFactorConfirmed)
                        <ol class="mb-3">
                            <li>Scan this QR code with your authenticator app.</li>
                            <li>Enter the 6-digit code the app shows to finish setup.</li>
                        </ol>
                        <div class="pa-qr mb-3" role="img" aria-label="QR code for your authenticator app">{!! $qrCode !!}</div>
                        <p class="small">Can't scan? Enter this key manually: <code class="user-select-all">{{ decrypt($user->two_factor_secret) }}</code></p>
                        <form method="POST" action="{{ route('two-factor.confirm') }}" class="row g-2 align-items-end" novalidate>
                            @csrf
                            @php($bag = $errors->getBag('confirmTwoFactorAuthentication'))
                            <div class="col-sm-6">
                                <label for="tfa-code" class="form-label">Authentication code</label>
                                <input id="tfa-code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" class="form-control @if ($bag->has('code')) is-invalid @endif" required>
                                @if ($bag->has('code'))<div class="invalid-feedback">{{ $bag->first('code') }}</div>@endif
                            </div>
                            <div class="col-sm-6">
                                <button type="submit" class="btn btn-primary">Confirm</button>
                            </div>
                        </form>
                    @else
                        <p>Two-factor authentication is active. You will be asked for a code from your authenticator app when you sign in.</p>

                        @if ($recoveryCodes)
                            <div class="alert alert-info pa-alert">
                                <p class="mb-2"><strong>Store these recovery codes somewhere safe.</strong> Each can be used once if you lose access to your authenticator app. They will not be shown again.</p>
                                <ul class="list-unstyled font-monospace mb-0 pa-recovery-codes">
                                    @foreach ($recoveryCodes as $code)
                                        <li>{{ $code }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <div class="d-flex flex-wrap gap-2">
                            <form method="POST" action="{{ route('two-factor.regenerate-recovery-codes') }}">
                                @csrf
                                <button type="submit" class="btn btn-outline-secondary">Generate new recovery codes</button>
                            </form>
                            @unless ($user->requiresTwoFactor())
                                <form method="POST" action="{{ route('two-factor.disable') }}" data-confirm="Disable two-factor authentication?">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-outline-danger">Disable</button>
                                </form>
                            @endunless
                        </div>
                    @endif
                </div>
            </section>
        </div>
    </div>
</x-admin.layout>
