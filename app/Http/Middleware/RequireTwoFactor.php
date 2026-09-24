<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Privileged roles (config pacms.security.two_factor_roles) must confirm two-factor
 * authentication before using the admin. Until then they are sent to the security page.
 */
class RequireTwoFactor
{
    /** Admin routes reachable while 2FA is still being set up. */
    private const ALLOWED_ROUTES = ['admin.account.security'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->requiresTwoFactor() && ! $user->hasConfirmedTwoFactor()
            && ! $request->routeIs(...self::ALLOWED_ROUTES)) {
            if ($request->expectsJson()) {
                abort(403, 'Two-factor authentication must be enabled for your account.');
            }

            return redirect()->route('admin.account.security')
                ->with('warning', __('Your role requires two-factor authentication. Please enable it to continue.'));
        }

        return $next($request);
    }
}
