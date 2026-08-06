<?php

namespace App\Http\Middleware;

use App\Support\AdminContext;
use Closure;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates a Supabase (GoTrue) ADMIN for the web console.
 *
 *  1. Read the access token from "Authorization: Bearer <jwt>" or session('supabase_token').
 *  2. Verify it. Modern Supabase projects sign tokens with ASYMMETRIC keys
 *     (ES256/RS256) → verified against the project JWKS (public keys, cached).
 *     Legacy projects sign HS256 with a shared secret → verified with
 *     config('supabase.jwt_secret'). The token's own `alg` header decides which.
 *  3. Load app role + organization_id from public.profiles by the token's `sub`
 *     — the GoTrue JWT does NOT carry them, so profiles is the source of truth,
 *     exactly like the RLS helpers current_app_role()/current_org_id().
 *  4. Reject anyone who is not an ACTIVE org_admin / super_admin.
 *
 * On success it fills the request-scoped {@see AdminContext} the tenant
 * OrganizationScope reads. No separate Laravel users table — GoTrue is the single
 * identity across mobile + web.
 */
class AuthenticateSupabaseAdmin
{
    private const JWKS_CACHE_KEY = 'supabase_jwks';

    /** Seconds an admin's role/is_active may be stale. See the note at the lookup. */
    private const PROFILE_CACHE_TTL = 30;

    public static function profileCacheKey(string $userId): string
    {
        return 'admin_profile:'.$userId;
    }

    /** Call after any write that changes a profile's role, is_active or full_name. */
    public static function forgetProfile(string $userId): void
    {
        Cache::forget(self::profileCacheKey($userId));
    }

    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->extractToken($request);
        if ($token === null) {
            return $this->deny($request, 'Missing bearer token.');
        }

        JWT::$leeway = 60; // tolerate minor clock skew
        try {
            $claims = $this->decodeToken($token);
        } catch (ExpiredException $e) {
            // The ~1h access token lapsed — try to silently renew it with the stored
            // refresh token instead of bouncing the admin to the login screen.
            $token = $this->attemptRefresh($request);
            if ($token === null) {
                return $this->deny($request, 'Your session expired. Please sign in again.');
            }

            try {
                $claims = $this->decodeToken($token);
            } catch (\Throwable $e2) {
                return $this->deny($request, 'Your session expired. Please sign in again.');
            }
        } catch (\Throwable $e) {
            return $this->deny($request, 'Invalid or expired token.');
        }

        $sub = $claims->sub ?? null;
        if (empty($sub)) {
            return $this->deny($request, 'Token has no subject.');
        }

        // Source of truth = public.profiles (NOT the JWT).
        //
        // Cached for a short window because this runs on EVERY request — including
        // static-ish ones and the notification poll — and the database is a hosted
        // Supabase instance ~400 ms away, so it was a visible tax on every page load.
        //
        // The TTL is deliberately short: this row carries `role` and `is_active`, so a
        // stale entry means a demoted or deactivated admin keeps console access until it
        // lapses. PROFILE_CACHE_TTL seconds is the maximum exposure. The write paths that
        // change those fields call forgetProfile() so the common cases are immediate;
        // this window only covers changes made OUTSIDE this app (e.g. straight from the
        // Supabase SQL editor). If you need revocation to be instant, drop the cache
        // rather than lengthening the TTL.
        $profile = Cache::remember(
            self::profileCacheKey($sub),
            now()->addSeconds(self::PROFILE_CACHE_TTL),
            fn () => DB::table('profiles')
                ->where('id', $sub)
                ->first(['id', 'full_name', 'role', 'is_active', 'organization_id'])
        );

        if ($profile === null || ! $profile->is_active) {
            return $this->deny($request, 'No active profile for this account.');
        }

        if (! in_array($profile->role, ['org_admin', 'super_admin'], true)) {
            return $this->deny($request, 'This account is not permitted in the admin console.');
        }

        app(AdminContext::class)->fill([
            'userId' => $profile->id,
            'role' => $profile->role,
            'organizationId' => $profile->organization_id,
            'email' => $claims->email ?? null,
            'fullName' => $profile->full_name,
        ]);

        return $next($request);
    }

    /**
     * Verify the token, choosing the algorithm from its own header.
     * ES256/RS256 → JWKS public keys; HS256 → shared secret.
     */
    private function decodeToken(string $token): object
    {
        if ($this->tokenAlg($token) === 'HS256') {
            $secret = config('supabase.jwt_secret');
            if (empty($secret)) {
                throw new \RuntimeException('HS256 token but SUPABASE_JWT_SECRET is not set.');
            }

            return JWT::decode($token, new Key($secret, 'HS256'));
        }

        // Asymmetric signing keys (default for current Supabase projects).
        try {
            return JWT::decode($token, JWK::parseKeySet($this->jwks(false)));
        } catch (ExpiredException $e) {
            throw $e; // a valid-but-expired token: don't burn a JWKS refetch on it
        } catch (\Throwable $e) {
            // Possible key rotation → bust cache and retry once with fresh JWKS.
            return JWT::decode($token, JWK::parseKeySet($this->jwks(true)));
        }
    }

    /**
     * Exchange the session's refresh token for a fresh access token via GoTrue.
     * Returns the new access token (and stores the rotated pair in the session) or
     * null when there's no refresh token or the exchange fails (revoked/expired).
     * Only the browser/session flow carries a refresh token — bearer API callers
     * manage their own tokens and simply get denied on expiry.
     */
    private function attemptRefresh(Request $request): ?string
    {
        if (! $request->hasSession()) {
            return null;
        }

        $refreshToken = $request->session()->get('supabase_refresh_token');
        if (empty($refreshToken)) {
            return null;
        }

        try {
            $response = Http::withHeaders(['apikey' => (string) config('supabase.anon_key')])
                ->acceptJson()
                ->asJson()
                ->timeout(15)
                ->post(rtrim((string) config('supabase.url'), '/').'/auth/v1/token?grant_type=refresh_token', [
                    'refresh_token' => $refreshToken,
                ]);
        } catch (\Throwable $e) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $access = $response->json('access_token');
        $newRefresh = $response->json('refresh_token');
        if (empty($access)) {
            return null;
        }

        // GoTrue rotates the refresh token on use — persist the new pair.
        $request->session()->put('supabase_token', $access);
        if (! empty($newRefresh)) {
            $request->session()->put('supabase_refresh_token', $newRefresh);
        }

        return $access;
    }

    /** Read the `alg` from the JWT header segment (url-safe base64, padded). */
    private function tokenAlg(string $token): ?string
    {
        $parts = explode('.', $token);
        if (count($parts) < 2) {
            return null;
        }

        $b64 = strtr($parts[0], '-_', '+/');
        $b64 = str_pad($b64, intdiv(strlen($b64) + 3, 4) * 4, '=');
        $header = json_decode((string) base64_decode($b64));

        return $header->alg ?? null;
    }

    /** Fetch (and cache) the project's JWKS public keys. */
    private function jwks(bool $fresh): array
    {
        if ($fresh) {
            Cache::forget(self::JWKS_CACHE_KEY);
        }

        return Cache::remember(self::JWKS_CACHE_KEY, now()->addHour(), function () {
            $url = rtrim((string) config('supabase.url'), '/').'/auth/v1/.well-known/jwks.json';
            $response = Http::acceptJson()->timeout(10)->get($url);
            abort_unless($response->successful(), 500, 'Could not fetch Supabase JWKS.');

            return $response->json();
        });
    }

    private function extractToken(Request $request): ?string
    {
        $bearer = $request->bearerToken();
        if (! empty($bearer)) {
            return $bearer;
        }

        $sessionToken = $request->hasSession() ? $request->session()->get('supabase_token') : null;

        return ! empty($sessionToken) ? $sessionToken : null;
    }

    private function deny(Request $request, string $message): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['error' => $message], 401);
        }

        // Browser flow: drop any stale/rejected tokens and send them to the login
        // screen with the reason. guest() remembers where they were headed so
        // AuthController::login() can `intended()` them back after signing in.
        if ($request->hasSession()) {
            $request->session()->forget(['supabase_token', 'supabase_refresh_token']);
        }

        return redirect()->guest(route('login'))->with('error', $message);
    }
}
