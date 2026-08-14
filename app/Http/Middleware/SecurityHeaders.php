<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline security response headers (audit 2026-08-14, L-3).
 *
 * The admin previously set none of these. Two matter more than the rest here:
 *
 *  - Bootstrap is loaded from a CDN, so a Content-Security-Policy is the control that
 *    would contain a future XSS or a CDN compromise. It is sent REPORT-ONLY for now:
 *    enforcing a policy that has never been exercised against every page is a good way
 *    to silently break the map editor or Live Ops mid-demo. Watch the browser console,
 *    then flip to enforcing.
 *
 *  - Referrer-Policy is not cosmetic on this app. Report pages embed short-lived signed
 *    URLs to private evidence media; without a referrer policy those URLs leak into the
 *    Referer header of whatever the admin clicks next.
 *
 * HSTS is deliberately conditional on the request already being HTTPS. Sending it over
 * plain HTTP is meaningless, and sending it from a local `php artisan serve` session can
 * pin a developer's browser to https://localhost, which is an unpleasant thing to debug.
 */
class SecurityHeaders
{
    /** Kept in one place so the CSP and the layout cannot drift apart. */
    private const CDN = 'https://cdn.jsdelivr.net';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');

        if ($request->secure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains'
            );
        }

        $supabase = rtrim((string) config('supabase.url'), '/');

        $csp = implode('; ', array_filter([
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' ".self::CDN,
            "style-src 'self' 'unsafe-inline' ".self::CDN,
            "font-src 'self' data: ".self::CDN,
            // Map tiles and org logos are remote images; evidence arrives as signed URLs.
            "img-src 'self' data: blob: https:",
            "media-src 'self' blob: https:",
            "connect-src 'self' ".$supabase.' https://tiles.openfreemap.org',
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ]));

        $response->headers->set('Content-Security-Policy-Report-Only', $csp);

        return $response;
    }
}
