<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * HTTP security headers for every response (SECURITY-ARCHITECTURE.md §10).
 *
 * Scripts are limited to 'self' plus a per-request nonce, which Vite adds to the tags it
 * renders. Inline styles are allowed because compiled block CSS and Bootstrap's
 * components use them; their values only come from validated input.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Vite::useCspNonce();

        $response = $next($request);

        $headers = $response->headers;
        $headers->set('Content-Security-Policy', $this->contentSecurityPolicy($nonce), false);
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'SAMEORIGIN');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');

        if (app()->isProduction() && $request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    private function contentSecurityPolicy(string $nonce): string
    {
        // During `npm run dev` assets come from the Vite dev server.
        $dev = Vite::isRunningHot() ? $this->viteDevOrigins() : '';

        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}'{$dev}",
            "style-src 'self' 'unsafe-inline'{$dev}",
            "img-src 'self' data: blob: https:{$dev}",
            "media-src 'self' https:",
            "font-src 'self' data:{$dev}",
            "connect-src 'self'{$dev}".($dev ? str_replace('http', 'ws', $dev) : ''),
            "frame-src 'self' https://www.youtube-nocookie.com https://player.vimeo.com https://www.facebook.com",
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "object-src 'none'",
        ]);
    }

    private function viteDevOrigins(): string
    {
        $hot = trim((string) @file_get_contents(public_path('hot')));
        $origin = rtrim($hot, '/');

        // CSP cannot express IPv6 literals ([::1]); vite.config.js binds the dev server to 127.0.0.1.
        if (str_contains($origin, '[')) {
            logger()->warning('Vite dev server runs on an IPv6 address; CSP will block its styles. Restart `npm run dev`.');
        }

        return $origin !== '' ? ' '.$origin : '';
    }
}
