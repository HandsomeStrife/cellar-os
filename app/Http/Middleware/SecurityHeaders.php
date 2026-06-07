<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Security headers added to all responses.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Prevent clickjacking
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // Prevent MIME type sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // XSS protection (legacy, but still useful for older browsers)
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // Referrer policy
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Permissions policy (restrict browser features)
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        // HSTS + CSP apply in production only. Locally, HSTS would force HTTPS
        // on *.cerberus.local, and the CSP would flag Vite's dev-server origin
        // (separate port) — neither reflects production, so we skip both in dev.
        if (app()->environment('production')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');

            // Content Security Policy (report-only until validated, then promote).
            $csp = implode('; ', [
                "default-src 'self'",
                "script-src 'self' 'unsafe-inline' 'unsafe-eval'",
                "style-src 'self' 'unsafe-inline' https://fonts.bunny.net",
                "font-src 'self' https://fonts.bunny.net",
                "img-src 'self' data: blob: https://*.amazonaws.com",
                "connect-src 'self'",
                "frame-ancestors 'self'",
            ]);
            $response->headers->set('Content-Security-Policy-Report-Only', $csp);
        }

        // Remove PHP version exposure
        header_remove('X-Powered-By');

        return $response;
    }
}
