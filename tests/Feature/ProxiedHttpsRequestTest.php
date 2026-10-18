<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Behind a TLS-terminating proxy, the console must still know it is on https.
 *
 * Render (like every PaaS) answers the browser over TLS and forwards plain HTTP to
 * the container. Until the framework was told to trust `X-Forwarded-Proto`, every
 * request looked like http to the application, and sign-in on the deployed console
 * hung on "Signing in…" because of it:
 *
 *   1. An anonymous visit to /dashboard reached AuthenticateSupabaseAdmin, which
 *      denies via redirect()->guest(). guest() stores $request->fullUrl() as the
 *      intended destination.
 *   2. fullUrl() reads the REQUEST's scheme, so URL::forceScheme('https') does not
 *      reach it. It stored http://…/dashboard.
 *   3. The admin signed in; AuthController::login() called redirect()->intended(),
 *      which replays an absolute URL verbatim — Location: http://…/dashboard.
 *   4. Chrome checks a form submission's redirect target against `form-action
 *      'self'`. A different scheme is a different origin, so it refused the
 *      navigation. The POST had already succeeded, so nothing errored: the button
 *      spun until the admin reloaded by hand and landed on a dashboard they were
 *      already signed in to.
 *
 * None of that is visible in a bug report. All anyone can say is "login is slow and
 * I have to refresh", which is why the mechanism is written out here.
 *
 * No database or network is touched: the middleware refuses before it reads a
 * profile, so these run against the real middleware stack with no backend.
 */
class ProxiedHttpsRequestTest extends TestCase
{
    /** As Render sends it: plain HTTP to the container, scheme stated in the header. */
    private const FORWARDED = [
        'X-Forwarded-Proto' => 'https',
        'X-Forwarded-Port' => '443',
    ];

    public function test_intended_url_kept_for_a_proxied_guest_is_https(): void
    {
        // The whole bug in one assertion. An http:// intended URL is a different
        // origin from the page the form was submitted from, and the browser — not
        // the server — is what refuses it, so nothing in a log would show this.
        $this->withHeaders(self::FORWARDED)
            ->get('http://amoec-track-admin.onrender.com/dashboard')
            ->assertRedirect();

        $intended = session('url.intended');

        $this->assertNotNull($intended, 'guest() must remember where the admin was headed');
        $this->assertStringStartsWith(
            'https://',
            $intended,
            'the intended URL must carry the scheme the BROWSER used, not the one the proxy spoke to us in'
        );
    }

    public function test_hsts_is_sent_on_a_proxied_https_request(): void
    {
        // Same root cause, quieter symptom: SecurityHeaders only emits HSTS on an
        // already-secure request, so while no request looked secure the deployed
        // console never sent it at all.
        $this->withHeaders(self::FORWARDED)
            ->get('http://amoec-track-admin.onrender.com/login')
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_an_unproxied_plain_http_request_is_still_treated_as_insecure(): void
    {
        // The trust must not be unconditional. Without the forwarded header a plain
        // request stays plain — this is what keeps `php artisan serve` from pinning a
        // developer's browser to https://localhost.
        $this->get('http://localhost/login')
            ->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_forwarded_host_is_not_trusted(): void
    {
        // X-Forwarded-Host is deliberately absent from the trusted set: nothing here
        // needs it, and honouring it would let a spoofed Host header decide what
        // hostname the console writes into its own links.
        $this->withHeaders(self::FORWARDED + ['X-Forwarded-Host' => 'evil.example.com'])
            ->get('http://amoec-track-admin.onrender.com/dashboard')
            ->assertRedirect();

        $this->assertStringNotContainsString(
            'evil.example.com',
            (string) session('url.intended'),
            'a spoofed X-Forwarded-Host must not reach a generated URL'
        );
    }
}
