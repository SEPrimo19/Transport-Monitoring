<?php

namespace Tests\Feature;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Every console route other than the guest auth screens must bounce an anonymous
 * caller to /login (browser) or 401 (JSON), and the web group must carry CSRF
 * verification.
 *
 * No database or network is touched: with no bearer token and no session token,
 * AuthenticateSupabaseAdmin denies before it reaches JWKS or the profiles lookup.
 */
class UnauthenticatedAccessTest extends TestCase
{
    private const PUBLIC_URIS = ['login', 'logout', 'up', '/'];

    public function test_every_console_route_redirects_an_anonymous_browser_to_login(): void
    {
        $checked = 0;
        foreach (Route::getRoutes() as $route) {
            if (in_array($route->uri(), self::PUBLIC_URIS, true) || str_starts_with($route->uri(), 'storage')) {
                continue;
            }

            $uri = '/'.preg_replace('/\{[^}]+\}/', '00000000-0000-0000-0000-000000000000', $route->uri());
            $method = collect($route->methods())->first(fn ($m) => ! in_array($m, ['HEAD', 'OPTIONS']));

            $response = $this->call($method, $uri);

            $this->assertTrue(
                $response->isRedirect(route('login')),
                "{$method} {$uri} should redirect an anonymous browser to /login, got {$response->getStatusCode()}"
            );
            $checked++;
        }

        $this->assertGreaterThan(40, $checked, 'route discovery found suspiciously few routes');
    }

    public function test_json_requests_without_a_token_get_401_not_data(): void
    {
        $this->getJson('/operations/data')->assertUnauthorized();
        $this->getJson('/notifications/pending')->assertUnauthorized();
    }

    public function test_garbage_and_unsigned_bearer_tokens_are_refused(): void
    {
        $this->withToken('not.a.jwt')->getJson('/operations/data')->assertUnauthorized();

        // alg=none must be refused by the algorithm allowlist before any key is chosen.
        $b64 = fn (string $j) => rtrim(strtr(base64_encode($j), '+/', '-_'), '=');
        $none = $b64('{"alg":"none","typ":"JWT"}').'.'.$b64('{"sub":"x"}').'.';
        $this->withToken($none)->getJson('/operations/data')->assertUnauthorized();
    }

    public function test_csrf_verification_is_in_the_web_group(): void
    {
        $this->app->make(Kernel::class);
        $web = app('router')->getMiddlewareGroups()['web'] ?? [];

        $this->assertContains(ValidateCsrfToken::class, $web);
    }
}
