<?php

namespace App\Http\Controllers;

use App\Http\Middleware\AuthenticateSupabaseAdmin;
use App\Support\LoginIdentifierPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;

/**
 * Browser login for the admin console. There is NO local users table — we
 * authenticate against Supabase GoTrue (the same identity provider the mobile
 * app uses) and stash the returned access token in the session. Every protected
 * route then runs through {@see AuthenticateSupabaseAdmin},
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
        // 'string', not 'email': a super_admin-created org admin without a real email
        // signs in with a bare USERNAME (see CreateOrgAdminLoginRequest), which is not
        // itself a valid email address — the 'email' rule would reject it before it
        // ever reached LoginIdentifierPolicy below.
        $credentials = $request->validate([
            // max:255 restores the only length bound the 'email' rule used to provide. Without
            // it an arbitrary string up to post_max_size is serialised and POSTed to GoTrue,
            // unauthenticated, where egulias previously rejected it in-process.
            'email' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        // Bare username → the synthetic admin-created address
        // (`<username>@admins.amoectrack.invalid`). See LoginIdentifierPolicy's
        // docblock for why this console only ever needs to resolve THAT namespace,
        // never the mobile app's driver one, despite both existing side by side.
        $email = LoginIdentifierPolicy::resolveAuthEmail($credentials['email']);

        // Guarded because this is the one unauthenticated endpoint that reaches the
        // network, and Http:: throws rather than returning a response when the host is
        // unreachable, DNS fails, or the 15s timeout expires. Unhandled, that surfaced
        // as a framework error page on the sign-in screen — a stack trace shown to
        // whoever is standing at the login form, which is the wrong audience for it.
        try {
            $response = Http::withHeaders(['apikey' => (string) config('supabase.anon_key')])
                ->acceptJson()
                ->asJson()
                ->timeout(15)
                ->post(rtrim((string) config('supabase.url'), '/').'/auth/v1/token?grant_type=password', [
                    'email' => $email,
                    'password' => $credentials['password'],
                ]);
        } catch (ConnectionException $e) {
            report($e);

            return back()
                ->withInput($request->only('email'))
                ->with('error', 'Could not reach the sign-in service. Check your connection and try again.');
        }

        $token = $response->successful() ? $response->json('access_token') : null;
        $refreshToken = $response->successful() ? $response->json('refresh_token') : null;

        if (empty($token)) {
            // GoTrue returns 400 for bad creds; never leak which field was wrong.
            return back()
                ->withInput($request->only('email'))
                ->with('error', 'Invalid email/username or password.');
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
