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

    public function test_content_security_policy_is_sent_in_report_only_mode(): void
    {
        // Report-only on purpose. Enforcing a policy that has never been exercised
        // against every page risks silently breaking the route map editor or Live Ops
        // mid-demo. This test pins that the header exists; flipping it to enforcing is
        // a deliberate later change that should also change this assertion.
        $response = $this->get('/login');

        $response->assertHeader('Content-Security-Policy-Report-Only');
        $this->assertStringContainsString(
            "frame-ancestors 'none'",
            $response->headers->get('Content-Security-Policy-Report-Only')
        );
    }

    public function test_hsts_is_not_sent_over_plain_http(): void
    {
        // Sending HSTS over HTTP is meaningless, and from a local `php artisan serve`
        // session it can pin a developer's browser to https://localhost — an
        // unpleasant thing to debug. It must appear only on secure requests.
        $this->get('/login')->assertHeaderMissing('Strict-Transport-Security');
    }
}
