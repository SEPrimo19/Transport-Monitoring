<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Guards the baseline security response headers added after the 2026-08-14 audit
 * (finding L-3), which found the admin was sending none of them.
 *
 * These assertions exist because headers are the kind of thing that disappears
 * silently: nothing breaks when they are removed, no page looks different, and the
 * loss is invisible until it matters. A test is the only thing that notices.
 *
 * The route used is `/login`, deliberately — it is the one page reachable without a
 * Supabase session, so these run without a database or a live backend.
 */
class SecurityHeadersTest extends TestCase
{
    public function test_clickjacking_and_sniffing_protections_are_sent(): void
    {
        $response = $this->get('/login');

        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_referrer_policy_is_sent(): void
    {
        // Not cosmetic here: report pages embed short-lived signed URLs to private
        // evidence media, and without this those URLs leak into the Referer header
        // of whatever the admin clicks next.
        $this->get('/login')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_content_security_policy_is_enforced_not_report_only(): void
    {
        // It spent its life as Report-Only, which computed the whole policy and then
        // blocked nothing. This pins the flip, and would have caught the fact that the
        // flip happened without this assertion being updated with it.
        $response = $this->get('/login');

        $response->assertHeader('Content-Security-Policy');
        $response->assertHeaderMissing('Content-Security-Policy-Report-Only');
        $this->assertStringContainsString(
            "frame-ancestors 'none'",
            $response->headers->get('Content-Security-Policy')
        );
    }

    public function test_connect_src_allows_every_host_the_browser_actually_calls(): void
    {
        // The regression this exists to prevent: enforcing the policy without listing
        // OSRM left "Generate route along roads" dead on the deployed console, and
        // omitting Nominatim killed "Find location" on both the terminal form and the
        // region picker. Neither failed loudly -- the browser refuses the request and
        // the feature reports a connection problem it does not have -- and the map
        // tiles beside them kept loading, because tiles are <img> and img-src allows
        // https:. So the symptom pointed at the network and not at this header.
        //
        // Any new host fetched from a Blade view has to be added to connect-src AND to
        // this list.
        $csp = $this->get('/login')->headers->get('Content-Security-Policy');

        $this->assertNotNull($csp);
        $connectSrc = collect(explode(';', $csp))
            ->map(fn (string $directive): string => trim($directive))
            ->first(fn (string $directive): bool => str_starts_with($directive, 'connect-src'));

        $this->assertNotNull($connectSrc, 'connect-src directive is missing entirely');

        foreach ([
            "'self'",
            'https://router.project-osrm.org',
            'https://nominatim.openstreetmap.org',
            'https://tiles.openfreemap.org',
            // The live "not in a known data breach" tick on every password field.
            // Blocked here, the line silently never turns green and the checklist
            // looks broken — which is exactly how it was reported.
            'https://api.pwnedpasswords.com',
        ] as $host) {
            $this->assertStringContainsString(
                $host,
                $connectSrc,
                "connect-src must allow {$host} or the feature using it fails silently"
            );
        }
    }

    public function test_hsts_is_sent_only_over_https(): void
    {
        // Sending HSTS over HTTP is meaningless, and from a local `php artisan serve`
        // session it can pin a developer's browser to https://localhost -- an
        // unpleasant thing to debug.
        //
        // Both schemes are requested explicitly. This test used to call $this->get()
        // with a bare path, which inherits APP_URL, so whether it passed depended on
        // whoever's .env it ran against -- an https APP_URL made it fail while the
        // middleware was behaving correctly.
        $this->get('http://localhost/login')
            ->assertHeaderMissing('Strict-Transport-Security');

        $this->get('https://localhost/login')
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }
}
