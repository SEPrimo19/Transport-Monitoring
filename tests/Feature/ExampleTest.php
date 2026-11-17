<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Smoke tests for the pages reachable without a Supabase session.
 *
 * `/` used to redirect to the dashboard, which made Laravel's stub (GET / is 200) fail for
 * a long time; a suite with a known-failing example trains everyone to ignore red. `/` is
 * now the public landing page, so the plain 200 check is correct again.
 */
class ExampleTest extends TestCase
{
    public function test_the_root_url_shows_the_public_landing_page(): void
    {
        // `/` used to redirect to the dashboard; it is now the public landing page
        // (see LandingPageTest for what it must contain).
        $this->get('/')->assertOk();
    }

    public function test_the_login_page_renders_for_a_guest(): void
    {
        // The only page an unauthenticated visitor can reach, and therefore the only
        // one testable without a live Supabase project.
        $this->get('/login')
            ->assertOk()
            ->assertSee('Sign in', false);
    }
}
