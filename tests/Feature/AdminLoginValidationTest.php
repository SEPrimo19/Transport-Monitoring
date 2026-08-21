<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * AuthController::login() relaxed `'email' => ['required', 'email']` to
 * `['required', 'string']` so a bare admin-created username (not itself a valid
 * email shape) can reach LoginIdentifierPolicy. This is the highest-risk change in
 * the batch: it is how every administrator — org_admin and super_admin alike —
 * signs in, so an over-permissive or broken rule here either lets garbage reach
 * GoTrue or locks out the whole console.
 *
 * Sibling of AdminLoginUsernameTest (which pins the two ACCEPTED shapes: bare
 * username and real email). This file pins the REJECTED shapes: empty, whitespace
 * only, and non-string input must still fail validation before anything is sent to
 * GoTrue. DB-free: AuthController::login() never touches the database, and a
 * validation failure never invokes Http at all — Http::fake() with no handlers
 * plus Http::assertNothingSent() proves that directly rather than by inference.
 */
class AdminLoginValidationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['supabase.url' => 'https://example.test', 'supabase.anon_key' => 'test-anon-key']);
        Http::fake();
    }

    public function test_an_empty_identifier_is_rejected_without_ever_calling_gotrue(): void
    {
        $response = $this->post('/login', [
            'email' => '',
            'password' => 'a-strong-password',
        ]);

        $response->assertSessionHasErrors('email');
        Http::assertNothingSent();
    }

    public function test_a_whitespace_only_identifier_is_rejected_without_ever_calling_gotrue(): void
    {
        // Laravel's `required` rule trims a string before checking emptiness, so this
        // must fail the same way an empty string does — not fall through to
        // LoginIdentifierPolicy and get sent to GoTrue as "   @admins.amoectrack.invalid".
        $response = $this->post('/login', [
            'email' => '   ',
            'password' => 'a-strong-password',
        ]);

        $response->assertSessionHasErrors('email');
        Http::assertNothingSent();
    }

    public function test_a_missing_identifier_field_is_rejected_without_ever_calling_gotrue(): void
    {
        $response = $this->post('/login', [
            'password' => 'a-strong-password',
        ]);

        $response->assertSessionHasErrors('email');
        Http::assertNothingSent();
    }

    public function test_a_non_string_identifier_is_rejected_without_ever_calling_gotrue(): void
    {
        // A crafted request (e.g. email[]=a&email[]=b) submits an array. The 'string'
        // rule must still catch this — LoginIdentifierPolicy::resolveAuthEmail()
        // takes a plain string and has nothing defensive of its own against an array
        // reaching it.
        $response = $this->post('/login', [
            'email' => ['a', 'b'],
            'password' => 'a-strong-password',
        ]);

        $response->assertSessionHasErrors('email');
        Http::assertNothingSent();
    }

    public function test_an_empty_password_is_rejected_without_ever_calling_gotrue(): void
    {
        $response = $this->post('/login', [
            'email' => 'jdoe',
            'password' => '',
        ]);

        $response->assertSessionHasErrors('password');
        Http::assertNothingSent();
    }

    public function test_a_missing_password_field_is_rejected_without_ever_calling_gotrue(): void
    {
        $response = $this->post('/login', [
            'email' => 'jdoe',
        ]);

        $response->assertSessionHasErrors('password');
        Http::assertNothingSent();
    }
}
