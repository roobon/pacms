<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;

/**
 * After login, staff go to the admin dashboard and registered users to their account page.
 * JSON clients (the public SPA) get a small JSON body instead of a redirect.
 */
class LoginResponse implements LoginResponseContract, TwoFactorLoginResponseContract
{
    public function toResponse($request)
    {
        if ($request->wantsJson()) {
            return new JsonResponse(['two_factor' => false]);
        }

        $home = $request->user()?->can('admin.access') ? route('admin.dashboard') : '/account';

        return redirect()->intended($home);
    }
}
