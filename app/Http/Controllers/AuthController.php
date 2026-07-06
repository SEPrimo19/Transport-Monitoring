<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;

/**
 * Browser login for the admin console. There is NO local users table — we
 * authenticate against Supabase GoTrue (the same identity provider the mobile
 * app uses) and stash the returned access token in the session. Every protected
 * route then runs through {@see \App\Http\Middleware\AuthenticateSupabaseAdmin},
 * which re-verifies that token and loads role + organization from public.profiles.
 *
 * We deliberately do NOT decide "is this an admin?" here. That check lives in one
 * place (the middleware), so a passenger/driver who signs in with valid creds is
 * bounced straight back to this screen with the middleware's own message. Single
 * source of truth for authorization.
 */
class AuthController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        // Already holding a token? Let the dashboard (and its middleware) decide.
        if ($request->session()->has('supabase_token')) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $response = Http::withHeaders(['apikey' => (string) config('supabase.anon_key')])
            ->acceptJson()
            ->asJson()
            ->timeout(15)
            ->post(rtrim((string) config('supabase.url'), '/').'/auth/v1/token?grant_type=password', [
                'email' => $credentials['email'],
                'password' => $credentials['password'],
            ]);

        $token = $response->successful() ? $response->json('access_token') : null;
        $refreshToken = $response->successful() ? $response->json('refresh_token') : null;

        if (empty($token)) {
            // GoTrue returns 400 for bad creds; never leak which field was wrong.
            return back()
                ->withInput($request->only('email'))
                ->with('error', 'Invalid email or password.');
        }

        // Rotate the session id on privilege change (fixation defence), THEN store
        // the tokens so they survive the regenerate. The refresh token lets the
        // middleware silently renew the ~1h access token instead of bouncing to login.
        $request->session()->regenerate();
        $request->session()->put('supabase_token', $token);
        if (! empty($refreshToken)) {
            $request->session()->put('supabase_refresh_token', $refreshToken);
        }

        // Authorization (active org_admin / super_admin) is enforced by the
        // supabase.admin middleware on the destination. `intended()` returns them
        // to wherever they were headed before being sent to login.
        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget(['supabase_token', 'supabase_refresh_token']);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'You have been signed out.');
    }
}
