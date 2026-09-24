<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate for the whole /admin area: an active account with the `admin.access` permission.
 * Individual screens still authorize their own actions through policies.
 */
class EnsureAdminAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user !== null && $user->isActive() && $user->can('admin.access'), 403);

        return $next($request);
    }
}
