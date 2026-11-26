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

    public function test_the_privacy_page_explains_account_deletion_in_plain_words(): void
    {
        // Deleting an account removes the person but keeps their reports. The page must say what
        // goes, what stays and why, and where to do it, or the in-app screen and this page disagree.
        $this->get('/privacy')
            ->assertOk()
            ->assertSee('Delete my account', false)                 // where a passenger does it
            ->assertSee('former passenger', false)                  // how a kept report reads afterwards
            ->assertSee('position recorded with the report is erased', false)
            ->assertSee('legitimate interest', false)               // why the reports stay
            ->assertSee('still records which account sent the report', false)   // existing promise kept
            // senior review of Task 9: what the page must not leave out or overclaim
            ->assertSee('keeps any details your camera stored', false)       // photo/video metadata
            ->assertSee('stays anonymous', false)                              // anonymous reports are not relabelled
            ->assertSee('service providers&rsquo; own logs', false)          // traces outside our tables
            ->assertSee('new, empty account', false);                          // same Google account again
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

    public function test_the_hero_button_scrolls_to_the_download_section_and_carries_the_file_address_for_android(): void
    {
        // On a computer the file is useless, so the button scrolls to the Download section (QR code included);
        // landing.js sends an Android phone straight to the file using data-apk.
        $this->get('/')
            ->assertSee('id="heroDownload" href="#download"', false)
            ->assertSee('data-apk="'.config('landing.apk_url').'"', false);
    }

    public function test_the_download_section_has_a_qr_code_for_visitors_on_a_computer(): void
    {
        $this->get('/')->assertSee('images/landing/download-qr.svg', false);

        $qr = file_get_contents(public_path('images/landing/download-qr.svg'));
        $this->assertStringContainsString(
            config('landing.apk_url'),
            $qr,
            'download-qr.svg was made for a different address: regenerate it when landing.apk_url changes'
        );
    }

    public function test_the_animation_can_be_paused_and_iphone_visitors_are_told_the_app_is_android_only(): void
    {
        $this->get('/')
            ->assertSee('id="motionToggle"', false)
            ->assertSee('id="iosNote"', false)
            ->assertSee('Android only for now', false);
    }

    public function test_the_contact_link_appears_when_configured_and_the_github_route_stays(): void
    {
        $this->get('/')->assertSee(config('landing.issues_url'), false)->assertDontSee('LANDING_CONTACT', false);

        config(['landing.contact_url' => 'https://m.me/example-page', 'landing.contact_label' => 'Message us']);
        $this->get('/')->assertSee('https://m.me/example-page', false)->assertSee('Message us', false);
        $this->get('/privacy')->assertSee('https://m.me/example-page', false);
    }

    public function test_the_sign_in_page_links_back_to_the_home_page_and_points_passengers_to_the_app(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Back to home', false)
            ->assertSee(route('home'), false)
            ->assertSee('Passenger or driver? Sign in on the Android app.', false)
            ->assertSee(route('home').'#download', false)
            ->assertSee('--amoec-brand: #165935', false);   // the landing page's green, not Bootstrap blue
    }

    public function test_the_health_route_the_keep_warm_workflow_calls_answers(): void
    {
        // .github/workflows/keep-warm.yml requests /up every 10 minutes; if this route goes, the pings fail quietly.
        $this->get('/up')->assertOk();
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
