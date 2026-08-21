<?php

namespace App\Support;

use App\Http\Controllers\AccountController;

/**
 * Calls the `driver-account` Supabase Edge Function
 * (AMOECTrack/supabase/functions/driver-account) to mint or reset an
 * admin-created driver login.
 *
 * This is the ONLY place the web admin touches minting a GoTrue user for a
 * driver — and it never sees `service_role`. That key lives exclusively in
 * the Edge Function's own Supabase secrets (see its README). This class
 * authenticates as the SIGNED-IN ADMIN, exactly like every other Supabase
 * call in this app (compare {@see AccountController},
 * {@see OrgLogoUploader}) — the Edge Function itself re-derives the caller's
 * identity and role from that token before doing anything privileged.
 *
 * The actual HTTP request/response handling is {@see SupabaseAccountCall},
 * shared with the org-admin sibling {@see OrgAdminAccountProvisioner} so that
 * plumbing exists in exactly one place.
 *
 * Never returns the raw HTTP/JSON response to a caller — see
 * {@see DriverAccountResult}.
 */
class DriverAccountProvisioner
{
    use SupabaseAccountCall;

    /**
     * @param  array{organization_id: ?string, full_name: string, employee_code: string, license_number: string, password: string}  $payload
     */
    public function create(string $sessionToken, array $payload): DriverAccountResult
    {
        $response = $this->callAccountFunction($sessionToken, ['action' => 'create', ...$payload]);
        if ($response === null) {
            // The 20s timeout in callAccountFunction() means the likeliest cause on a
            // provincial link is the Edge Function having SUCCEEDED and the response
            // being lost — not a rejection. Telling the admin to just "try again" invites
            // a second driver row for the same person with a login nobody knows the
            // password to.
            return DriverAccountResult::unreachable(
                'The connection dropped before we could confirm. Check the driver list before '.
                'trying again — the account may already have been created.'
            );
        }

        if (! $response->successful()) {
            $message = $this->accountErrorMessage($response);
            if ($response->status() === 409) {
                // The common case (see storeLogin()'s caller-facing note): a 409 here
                // reads as "pick a different username", but it's also exactly what a
                // retry after a lost-response timeout looks like.
                $message .= ' If you just created this driver, check the driver list — they may already be there.';
            }

            return DriverAccountResult::failed($message);
        }

        return DriverAccountResult::ok(
            profileId: $response->json('profile_id'),
            email: $response->json('email'),
        );
    }

    public function resetPassword(string $sessionToken, string $profileId, string $password): DriverAccountResult
    {
        $response = $this->callAccountFunction($sessionToken, [
            'action' => 'reset_password',
            'profile_id' => $profileId,
            'password' => $password,
        ]);

        if ($response === null) {
            // Unlike create(), this action is idempotent server-side (updateUserById just
            // sets the password again) — so unlike create(), the safe instruction is to
            // retry, but with the SAME password. Retrying with a different one leaves the
            // admin unsure which one is actually set if the first call did land.
            return DriverAccountResult::unreachable(
                'The connection dropped before we could confirm. Retry with the SAME password — '.
                'this action is safe to repeat.'
            );
        }

        if (! $response->successful()) {
            return DriverAccountResult::failed($this->accountErrorMessage($response));
        }

        return DriverAccountResult::ok();
    }

    /**
     * Release an admin-created login's username so it can be reused. A no-op for a
     * promoted driver's personal email — the Edge Function decides that itself, from
     * the real auth.users row, never from anything this app claims.
     */
    public function deleteAccount(string $sessionToken, string $profileId): DriverAccountResult
    {
        $response = $this->callAccountFunction($sessionToken, [
            'action' => 'delete_account',
            'profile_id' => $profileId,
        ]);

        if ($response === null) {
            // Called from destroy() BEFORE the roster row is touched, so a lost response
            // here has not (from this app's side) deleted anything else yet — but we
            // still can't tell whether the Edge Function itself completed.
            return DriverAccountResult::unreachable(
                'The connection dropped before we could confirm the login was removed. Check the '.
                'driver list before trying again.'
            );
        }

        if (! $response->successful()) {
            return DriverAccountResult::failed($this->accountErrorMessage($response));
        }

        return DriverAccountResult::ok(accountDeleted: (bool) $response->json('deleted'));
    }
}
