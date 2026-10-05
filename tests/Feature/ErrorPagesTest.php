<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The console had no resources/views/errors/ at all, so every HTTP error fell through
 * to the framework's own page: a full stack trace while APP_DEBUG is on, and a bare
 * unbranded message once it is off.
 *
 * These views are deliberately standalone rather than @extends('layouts.app'), because
 * that layout reads $brandColor and the AdminContext that AuthenticateSupabaseAdmin
 * fills in — neither of which exists for a signed-out visitor or for a fault thrown
 * before the middleware ran. A layout that threw while rendering the error page would
 * turn a handled error into an unhandled one. That is the property this file pins:
 * each page must render with NO session, NO organization, and NO admin context.
 *
 * DB-free: none of these views reads a model.
 */
class ErrorPagesTest extends TestCase
{
    public static function errorCodeProvider(): array
    {
        return [
            '403 forbidden' => [403, 'Not allowed'],
            '404 not found' => [404, 'Page not found'],
            '419 expired' => [419, 'Your session expired'],
            '429 throttled' => [429, 'Too many requests'],
            '500 server error' => [500, 'Something went wrong'],
            '503 unavailable' => [503, 'Temporarily unavailable'],
        ];
    }

    #[DataProvider('errorCodeProvider')]
    public function test_each_error_page_renders_without_a_session_or_tenant(int $code, string $heading): void
    {
        $html = view('errors.'.$code)->render();

        $this->assertStringContainsString($heading, $html);
        // Signed out, so the way back is sign-in, not the dashboard it would bounce off.
        $this->assertStringContainsString('Go to sign in', $html);
        $this->assertStringContainsString('<!doctype html>', $html);
    }

    #[DataProvider('errorCodeProvider')]
    public function test_no_error_page_leaks_internals(int $code, string $heading): void
    {
        $html = view('errors.'.$code)->render();

        // Whatever the cause, the page says what happened in plain words and nothing
        // about how the application is built. With APP_DEBUG off in a deployment these
        // pages are the only thing standing between an internal fault and a stranger.
        foreach (['Exception', 'Stack trace', 'vendor/', 'app/Http', 'Laravel'] as $leak) {
            $this->assertStringNotContainsString($leak, $html, "errors.{$code} leaks '{$leak}'");
        }
    }

    public function test_an_unknown_url_renders_the_404_page_rather_than_the_framework_default(): void
    {
        $response = $this->get('/a-page-that-does-not-exist');

        $response->assertStatus(404);
        $response->assertSee('Page not found');
    }
}
