<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The public pages: the landing page at "/" and the privacy page at "/privacy".
 *
 * DB-free like the rest of the suite: both pages read configuration only. What is pinned
 * here is what the pages are for, so a later edit cannot quietly lose the download link,
 * the sign-in link, or the privacy page the app depends on.
 */
class LandingPageTest extends TestCase
{
    public function test_the_landing_page_introduces_the_project_with_download_and_sign_in(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Know where your bus is', false)
            ->assertSee('Download Android app', false)
            ->assertSee('Administrator sign-in', false)
            ->assertSee(config('landing.apk_url'), false)
            ->assertSee(route('login'), false)
            ->assertSee(route('privacy'), false);
    }

    public function test_the_download_link_uses_githubs_latest_release_so_it_survives_new_versions(): void
    {
        $this->assertStringContainsString('/releases/latest/download/', config('landing.apk_url'));
    }

    public function test_the_landing_page_lists_the_checksum_and_the_manual(): void
    {
        $this->get('/')
            ->assertSee(config('landing.sha256'), false)
            ->assertSee(asset(config('landing.manual_path')), false);
    }

    public function test_a_visitor_with_an_admin_session_is_offered_the_dashboard_instead_of_sign_in(): void
    {
        $this->withSession(['supabase_token' => 'present'])
            ->get('/')
            ->assertOk()
            ->assertSee('Open dashboard', false)
            // the sign-in buttons carry this icon; the footer link (always present) does not
            ->assertDontSee('bi-box-arrow-in-right', false);
    }

    public function test_the_privacy_page_renders_and_states_the_essentials(): void
    {
        $this->get('/privacy')
            ->assertOk()
            ->assertSee('What we collect', false)
            ->assertSee('How long we keep it', false)
            ->assertSee('Submit anonymously', false);
    }

    public function test_the_privacy_page_discloses_what_the_review_found_missing(): void
    {
        // Pinned from a code review: these are real data flows, and the page must keep naming them.
        $this->get('/privacy')
            ->assertOk()
            ->assertSee('licence number', false)          // driver details held by the cooperative
            ->assertSee('Nominatim', false)               // coordinates leave the app for place names
            ->assertSee('Have I Been Pwned', false)       // password check in the console
            ->assertSee('GitHub', false)                  // hosts the APK
            ->assertSee('still records which account sent the report', false)   // anonymity is a console-side mask
            ->assertSee('Cookies', false);
    }

    public function test_the_page_stays_readable_when_the_script_does_not_load(): void
    {
        // The head falls back to the plain page after 2.5 s unless landing.js announces itself.
        $html = $this->get('/')->getContent();
        $this->assertStringContainsString('__landingReady', $html);
        $this->assertStringContainsString('no-motion', $html);
        $this->assertStringContainsString('no-motion', file_get_contents(public_path('css/landing.css')));
        $this->assertStringContainsString('__landingReady', file_get_contents(public_path('js/landing.js')));
    }

    public function test_the_checksum_is_copied_from_a_data_attribute_not_from_visible_text(): void
    {
        $this->get('/')->assertSee('data-hash="'.config('landing.sha256').'"', false);
    }

    public function test_the_public_pages_do_not_expose_the_console(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_every_image_and_asset_the_landing_page_names_exists(): void
    {
        $html = $this->get('/')->getContent();
        // Same-origin files only: the CDN addresses also contain "/css/" and "/js/".
        preg_match_all('#'.preg_quote(rtrim(url('/'), '/'), '#').'/((?:images/landing|css|js|downloads)/[A-Za-z0-9._-]+)#', $html, $m);
        $files = array_unique($m[1]);

        $this->assertNotEmpty($files);
        foreach ($files as $file) {
            $this->assertFileExists(public_path($file), "landing page references a missing file: {$file}");
        }
    }
}
