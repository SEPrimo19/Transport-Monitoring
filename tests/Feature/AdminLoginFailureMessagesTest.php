<?php

namespace Tests\Feature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Every way the sign-in screen can FAIL must end as a sentence on that screen, never
 * as a framework error page.
 *
 * This is a security boundary, not only a courtesy: /login is the one route an
 * unauthenticated stranger can reach, so a stack trace rendered there hands out file
 * paths, framework and PHP versions, and fragments of configuration to exactly the
 * audience that should not have them. It is also the single most likely place for an
 * error to surface, because mistyping a password is the most common thing anyone does
 * here.
 *
 * Sibling of AdminLoginValidationTest (rejected input shapes) and AdminLoginUsernameTest
 * (accepted identifier shapes). This file pins the three failure paths that reach GoTrue
 * or the rate limiter. DB-free, like both siblings: AuthController::login() never
 * touches the database, and Http::fake() means no request leaves the machine.
 */
class AdminLoginFailureMessagesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['supabase.url' => 'https://example.test', 'supabase.anon_key' => 'test-anon-key']);
        // The throttle middleware counts through the cache store (array, per phpunit.xml).
        // Flushed so a test that deliberately exhausts the limit cannot leak its count
        // into whichever test happens to run next.
        cache()->flush();
    }

    public function test_a_wrong_password_returns_to_the_login_screen_with_a_message(): void
    {
        // GoTrue answers bad credentials with 400 and an error body.
        Http::fake(['*/auth/v1/token*' => Http::response([
            'error' => 'invalid_grant',
            'error_description' => 'Invalid login credentials',
        ], 400)]);

        $response = $this->from('/login')->post('/login', [
            'email' => 'jdoe',
            'password' => 'the-wrong-password',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHas('error', 'Invalid email/username or password.');
        // The identifier survives so the admin only retypes the password.
        $response->assertSessionHasInput('email', 'jdoe');
        // Nothing from a failed grant may be kept.
        $response->assertSessionMissing('supabase_token');
    }

    public function test_an_unreachable_auth_service_returns_a_message_rather_than_an_exception(): void
    {
        // What a dead DNS entry, a refused connection, or the 15s timeout actually does:
        // Http:: throws instead of returning a response. Before this was caught, that
        // rendered the framework's error page on the public sign-in screen.
        Http::fake(fn () => throw new ConnectionException('cURL error 6: Could not resolve host'));

        $response = $this->from('/login')->post('/login', [
            'email' => 'jdoe',
            'password' => 'a-strong-password',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHas(
            'error',
            'Could not reach the sign-in service. Check your connection and try again.'
        );
        $response->assertSessionMissing('supabase_token');
    }

    public function test_exhausting_the_rate_limit_returns_a_message_rather_than_a_429_page(): void
    {
        Http::fake(['*/auth/v1/token*' => Http::response(['error' => 'invalid_grant'], 400)]);

        // POST /login is throttled at 8 per minute per IP. Someone who has genuinely
        // forgotten their password reaches that limit easily, which made this the most
        // probable route to a raw error page in ordinary use.
        for ($attempt = 1; $attempt <= 8; $attempt++) {
            $this->from('/login')->post('/login', [
                'email' => 'jdoe',
                'password' => 'guess-'.$attempt,
            ])->assertRedirect('/login');
        }

        $response = $this->from('/login')->post('/login', [
            'email' => 'jdoe',
            'password' => 'guess-9',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHas(
            'error',
            'Too many sign-in attempts. Please wait a minute and try again.'
        );
        $response->assertSessionHasInput('email', 'jdoe');
    }
}
