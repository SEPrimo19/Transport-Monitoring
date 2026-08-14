<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Smoke tests for the two routes reachable without a Supabase session.
 *
 * This replaces Laravel's stub, which asserted `GET /` returns 200. It does not, and
 * never has in this application: `/` is a redirect to the dashboard (routes/web.php),
 * so the stub had been failing since the day that redirect was added. A test suite with
 * a known-failing example in it trains everyone to ignore red, which is worse than
 * having no suite at all.
 */
class ExampleTest extends TestCase
{
    public function test_the_root_url_redirects_to_the_dashboard(): void
    {
        $this->get('/')->assertRedirect('/dashboard');
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
