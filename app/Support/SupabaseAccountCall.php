<?php

namespace App\Support;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Shared HTTP plumbing for calling the `driver-account` Supabase Edge Function
 * (AMOECTrack repo: supabase/functions/driver-account), used by both
 * {@see DriverAccountProvisioner} (drivers) and {@see OrgAdminAccountProvisioner}
 * (org admins) so the request/response handling — auth headers, timeout, transport
 * failure handling, error-message extraction — lives in exactly one place rather
 * than being copy-pasted between the two callers.
 *
 * Authenticates as the SIGNED-IN admin's own session token, never service_role —
 * see the Edge Function's own docblock for why.
 */
trait SupabaseAccountCall
{
    private function callAccountFunction(string $sessionToken, array $body): ?Response
    {
        $base = rtrim((string) config('supabase.url'), '/');
        $anon = (string) config('supabase.anon_key');

        if ($base === '' || $anon === '' || $sessionToken === '') {
            return null;
        }

        try {
            return Http::withHeaders([
                'apikey' => $anon,
                'Authorization' => 'Bearer '.$sessionToken,
            ])->acceptJson()->asJson()->timeout(20)
                ->post($base.'/functions/v1/driver-account', $body);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** The Edge Function always replies with {"error": "<plain language>"} on failure. */
    private function accountErrorMessage(Response $response): string
    {
        $msg = $response->json('error');

        return is_string($msg) && $msg !== '' ? $msg : 'The account service rejected the request. Please try again.';
    }
}
