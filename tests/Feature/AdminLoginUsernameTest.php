<?php

namespace Tests\Feature;

use App\Support\OrgAdminAccountEmail;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * AuthController::login() resolves whatever is typed in the "Email or username"
 * field through LoginIdentifierPolicy BEFORE it is sent to GoTrue's password
 * grant. Pins the two shapes: a bare username gets the admin `.invalid` domain
 * appended; a real email is passed through untouched.
 */
class AdminLoginUsernameTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['supabase.url' => 'https://example.test', 'supabase.anon_key' => 'test-anon-key']);
    }

    public function test_logging_in_with_a_bare_username_appends_the_admin_domain_before_calling_gotrue(): void
    {
        Http::fake([
            '*/auth/v1/token*' => Http::response(['access_token' => 'tok', 'refresh_token' => 'ref'], 200),
        ]);

        $this->post('/login', [
            'email' => 'jdoe',
            'password' => 'a-strong-password',
        ]);

        Http::assertSent(function ($request) {
            $body = $request->data();

            return ($body['email'] ?? null) === 'jdoe@'.OrgAdminAccountEmail::DOMAIN;
        });
    }

    public function test_logging_in_with_a_real_email_is_passed_through_unchanged(): void
    {
        Http::fake([
            '*/auth/v1/token*' => Http::response(['access_token' => 'tok', 'refresh_token' => 'ref'], 200),
        ]);

        $this->post('/login', [
            'email' => 'admin@example.com',
            'password' => 'a-strong-password',
        ]);

        Http::assertSent(function ($request) {
            $body = $request->data();

            return ($body['email'] ?? null) === 'admin@example.com';
        });
    }

    public function test_a_bare_username_is_no_longer_rejected_by_server_side_email_format_validation(): void
    {
        // Before this change, 'email' => ['required', 'email'] rejected a bare
        // username before it ever reached GoTrue or LoginIdentifierPolicy.
        Http::fake([
            '*/auth/v1/token*' => Http::response(['access_token' => 'tok', 'refresh_token' => 'ref'], 200),
        ]);

        $response = $this->post('/login', [
            'email' => 'jdoe',
            'password' => 'a-strong-password',
        ]);

        $response->assertSessionHasNoErrors();
    }
}
