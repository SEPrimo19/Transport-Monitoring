<?php

namespace Tests\Feature;

use App\Http\Middleware\AuthenticateSupabaseAdmin;
use App\Support\AdminContext;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * DriverController::storeLogin() resolves organization_id from AdminContext for an
 * org_admin — never from request input. CreateDriverLoginRequest::rules() only adds
 * an organization_id rule for a super_admin, so an org_admin's $data never even
 * carries the key; the controller falls back to $ctx->organizationId unconditionally.
 * This pins that an org_admin posting a spoofed organization_id for ANOTHER tenant
 * still ends up with their OWN org on the outbound call to the driver-account Edge
 * Function — the one place that write actually happens.
 *
 * AuthenticateSupabaseAdmin is bypassed rather than exercised: it verifies a real
 * GoTrue JWT against JWKS and reads a live `profiles` row, which is a live-backend
 * dependency unrelated to what this test pins. AdminContext is filled directly
 * instead, matching what that middleware would have done on success.
 */
class DriverLoginOrgScopeTest extends TestCase
{
    public function test_an_org_admin_cannot_spoof_the_organization_id_on_a_created_login(): void
    {
        config(['supabase.url' => 'https://example.test', 'supabase.anon_key' => 'test-anon-key']);

        Http::fake([
            '*/functions/v1/driver-account' => Http::response([
                'profile_id' => (string) Str::uuid(),
                'email' => 'jsantos@drivers.amoectrack.invalid',
            ], 200),
        ]);

        $ownOrgId = (string) Str::uuid();
        $someoneElsesOrgId = (string) Str::uuid();

        $ctx = $this->app->make(AdminContext::class);
        $ctx->fill([
            'userId' => (string) Str::uuid(),
            'role' => 'org_admin',
            'organizationId' => $ownOrgId,
        ]);

        $this->withoutMiddleware(AuthenticateSupabaseAdmin::class);

        $this->withSession(['supabase_token' => 'test-session-token'])->post('/drivers/login', [
            'full_name' => 'Juan Dela Cruz',
            'employee_code' => 'e2e-'.Str::random(10),
            'license_number' => 'lic-'.Str::random(10),
            'password' => 'a-strong-password',
            // Spoofed: an org_admin has no organization_id field to legitimately
            // submit at all — this simulates a crafted request trying anyway.
            'organization_id' => $someoneElsesOrgId,
        ]);

        Http::assertSent(function ($request) use ($ownOrgId, $someoneElsesOrgId) {
            $body = $request->data();

            return ($body['action'] ?? null) === 'create'
                && ($body['organization_id'] ?? null) === $ownOrgId
                && ($body['organization_id'] ?? null) !== $someoneElsesOrgId;
        });
    }
}
