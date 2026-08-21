<?php

namespace App\Support;

/**
 * Calls the `driver-account` Supabase Edge Function
 * (AMOECTrack/supabase/functions/driver-account) to mint or reset an
 * admin-created ORG ADMIN login — the org-admin sibling of
 * {@see DriverAccountProvisioner}. Shares the same HTTP request/response handling
 * via {@see SupabaseAccountCall} rather than duplicating it.
 *
 * Only a super_admin may successfully call `create_org_admin` /
 * `reset_org_admin_password` — the Edge Function enforces that itself from the
 * caller's own session token (never from anything this app claims), exactly like the
 * driver actions enforce org_admin/super_admin. `OrgAdminController` is already
 * gated behind the `super.admin` route middleware, so in practice this class is
 * never even reached by an org_admin — but the Edge Function is the actual boundary.
 *
 * Never returns the raw HTTP/JSON response to a caller — see {@see DriverAccountResult}.
 */
class OrgAdminAccountProvisioner
{
    use SupabaseAccountCall;

    /**
     * @param  array{organization_id: string, full_name: string, username: string, password: string, email: ?string}  $payload
     */
    public function create(string $sessionToken, array $payload): DriverAccountResult
    {
        $response = $this->callAccountFunction($sessionToken, ['action' => 'create_org_admin', ...$payload]);
        if ($response === null) {
            // Same reasoning as DriverAccountProvisioner::create(): the likeliest cause of
            // no response on a slow link is the Edge Function having SUCCEEDED, not
            // rejected — telling the operator to just retry risks a second account for
            // the same person with a login nobody knows the password to.
            return DriverAccountResult::unreachable(
                'The connection dropped before we could confirm. Check the org admin list before '.
                'trying again — the account may already have been created.'
            );
        }

        if (! $response->successful()) {
            $message = $this->accountErrorMessage($response);
            if ($response->status() === 409) {
                $message .= ' If you just created this admin, check the org admin list — they may already be there.';
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
            'action' => 'reset_org_admin_password',
            'profile_id' => $profileId,
            'password' => $password,
        ]);

        if ($response === null) {
            // Idempotent server-side (updateUserById just sets the password again), so
            // the safe instruction is to retry with the SAME password — mirrors
            // DriverAccountProvisioner::resetPassword().
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
}
