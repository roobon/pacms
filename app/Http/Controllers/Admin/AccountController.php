<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The signed-in user's own profile and security settings.
 * Forms post to Fortify's endpoints (/auth/user/...), which validate and apply the changes.
 */
class AccountController extends Controller
{
    public function profile(Request $request): View
    {
        return view('admin.account.profile', ['user' => $request->user()]);
    }

    public function security(Request $request): View
    {
        $user = $request->user();

        // Recovery codes are only displayed right after they are (re)generated or 2FA is confirmed.
        $showCodes = in_array(session('status'), ['two-factor-authentication-confirmed', 'recovery-codes-generated'], true);

        return view('admin.account.security', [
            'user' => $user,
            'twoFactorEnabled' => $user->two_factor_secret !== null,
            'twoFactorConfirmed' => $user->hasConfirmedTwoFactor(),
            'recoveryCodes' => $showCodes && $user->two_factor_secret !== null ? $user->recoveryCodes() : [],
            'qrCode' => $user->two_factor_secret !== null && ! $user->hasConfirmedTwoFactor() ? $user->twoFactorQrCodeSvg() : null,
        ]);
    }
}
