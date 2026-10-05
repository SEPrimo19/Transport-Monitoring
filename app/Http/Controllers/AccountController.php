<?php

namespace App\Http\Controllers;

use App\Http\Middleware\AuthenticateSupabaseAdmin;
use App\Support\AdminContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;
use App\Support\AdminPasswordPolicy;

/**
 * Self-service account settings for the signed-in admin (org_admin or super_admin):
 * change the display name and, optionally, the password.
 *
 * There is NO service_role key in this app (see config/supabase.php), so password
 * changes go through GoTrue as the USER, never the admin API:
 *   1. Re-authenticate with the CURRENT password (password grant) — this both
 *      proves it's really them and returns a guaranteed-fresh access token.
 *   2. PUT /auth/v1/user with that token to set the new password.
 *   3. Re-login with the NEW password to refresh the session token pair, because
 *      GoTrue may rotate/revoke the old refresh token on a password change.
 *
 * The display name lives in public.profiles (the same source of truth the auth
 * middleware and mobile app read), so it's updated there directly.
 */
class AccountController extends Controller
{
    public function edit(AdminContext $ctx): View
    {
        return view('account.edit', ['ctx' => $ctx]);
    }

    public function update(Request $request, AdminContext $ctx): RedirectResponse
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'min:2', 'max:120'],
            'current_password' => ['nullable', 'required_with:new_password', 'string'],
            'new_password' => array_merge(
                ['nullable'],
                array_values(array_filter(
                    AdminPasswordPolicy::rules(confirmed: true),
                    fn ($r) => $r !== 'required'
                )),
            ),
        ], [
            'current_password.required_with' => 'Enter your current password to set a new one.',
            'new_password.confirmed' => 'The new password and its confirmation do not match.',
            'new_password.min' => 'Use at least 8 characters for the new password.',
        ]);

        // Display name — always safe to persist (source of truth = profiles).
        DB::table('profiles')->where('id', $ctx->userId)->update([
            'full_name' => trim($data['full_name']),
        ]);

        // The header shows this name from the middleware's cached profile row; without
        // the eviction the admin would rename themselves and appear not to have.
        AuthenticateSupabaseAdmin::forgetProfile((string) $ctx->userId);

        // Password change is optional — only when a new password was supplied.
        if (! empty($data['new_password'])) {
            $result = $this->changePassword($request, $ctx, $data['current_password'], $data['new_password']);
            if ($result !== null) {
                return back()->withInput($request->only('full_name'))->with('error', $result);
            }

            return redirect()->route('account.edit')
                ->with('status', 'Your name and password were updated.');
        }

        return redirect()->route('account.edit')->with('status', 'Your name was updated.');
    }

    /**
     * Perform the three-step GoTrue password change. Returns null on success or a
     * human-readable error message on failure (nothing is committed to the auth
     * side on failure — the name update above is independent and already saved).
     */
    private function changePassword(Request $request, AdminContext $ctx, string $currentPassword, string $newPassword): ?string
    {
        $base = rtrim((string) config('supabase.url'), '/');
        $anon = (string) config('supabase.anon_key');

        if (empty($ctx->email)) {
            return 'Your account email is unavailable — sign out and in again, then retry.';
        }

        // 1. Re-authenticate with the current password.
        try {
            $reauth = Http::withHeaders(['apikey' => $anon])->acceptJson()->asJson()->timeout(15)
                ->post($base.'/auth/v1/token?grant_type=password', [
                    'email' => $ctx->email,
                    'password' => $currentPassword,
                ]);
        } catch (\Throwable $e) {
            return 'Could not reach the authentication service. Please try again.';
        }

        $freshToken = $reauth->successful() ? $reauth->json('access_token') : null;
        if (empty($freshToken)) {
            return 'Your current password is incorrect.';
        }

        // 2. Update the password as the user.
        try {
            $update = Http::withHeaders([
                'apikey' => $anon,
                'Authorization' => 'Bearer '.$freshToken,
            ])->acceptJson()->asJson()->timeout(15)
                ->put($base.'/auth/v1/user', ['password' => $newPassword]);
        } catch (\Throwable $e) {
            return 'Could not reach the authentication service. Please try again.';
        }

        if (! $update->successful()) {
            // GoTrue rejects a password equal to the old one, or one that fails its
            // strength policy — surface its message when it's safe/useful.
            $msg = $update->json('msg') ?? $update->json('error_description') ?? $update->json('message');

            return $msg ?: 'The password could not be updated. Choose a different password and try again.';
        }

        // 3. Re-login with the new password so the session keeps a valid token pair.
        try {
            $relogin = Http::withHeaders(['apikey' => $anon])->acceptJson()->asJson()->timeout(15)
                ->post($base.'/auth/v1/token?grant_type=password', [
                    'email' => $ctx->email,
                    'password' => $newPassword,
                ]);

            if ($relogin->successful() && ! empty($relogin->json('access_token'))) {
                $request->session()->put('supabase_token', $relogin->json('access_token'));
                if (! empty($relogin->json('refresh_token'))) {
                    $request->session()->put('supabase_refresh_token', $relogin->json('refresh_token'));
                }
            }
        } catch (\Throwable $e) {
            // Password DID change; only the session refresh failed. The current
            // access token stays valid until it expires — no error to the user.
        }

        return null;
    }
}
