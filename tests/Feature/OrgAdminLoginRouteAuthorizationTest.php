<?php

namespace Tests\Feature;

use App\Http\Middleware\AuthenticateSupabaseAdmin;
use App\Support\AdminContext;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The authorization matrix for the two NEW super_admin-only account-provisioning
 * routes: org-admins.login.store (create_org_admin) and org-admins.reset-password
 * (reset_org_admin_password). Unlike the driver equivalents, BOTH of these must
 * reject an org_admin — that asymmetry (create allows both tiers, create_org_admin
 * does not) is exactly the thing most likely to have been copy-pasted wrong, so
 * this pins it at the actual dispatched-HTTP-request level rather than trusting
 * the route table alone (see RouteTenantGateCoverageTest) or the middleware in
 * isolation (see RequireSuperAdminGateTest).
 *
 * AuthenticateSupabaseAdmin is bypassed rather than exercised — same rationale as
 * DriverLoginOrgScopeTest: it verifies a real GoTrue JWT against JWKS and reads a
 * live `profiles` row, a live-backend dependency unrelated to what this test pins.
 * AdminContext is filled directly instead, matching what that middleware would have
 * done on success. RequireSuperAdmin is NOT bypassed — it is the actual boundary
 * under test.
 *
 * DB-free by construction: every case here that is REFUSED is refused by
 * RequireSuperAdmin, which runs before SubstituteBindings (see
 * TenantGatePrecedesRouteBindingTest) — so a fabricated, non-existent {profile} UUID
 * for org-admins.reset-password never reaches a route-model-binding lookup. Http is
 * faked with no handlers registered, so `Http::assertNothingSent()` also proves the
 * driver-account Edge Function — the only place service_role-equivalent power is
 * exercised — was never called for any refused request.
 *
 * The super_admin-ALLOWED side of the matrix is deliberately NOT exercised here as a
 * full round trip: CreateOrgAdminLoginRequest's organization_id carries an
 * unconditional Rule::exists (no org_admin tier to fall back to, per its own
 * docblock), so a genuine POST as super_admin would issue a real SELECT against the
 * live organizations table even with an empty body (Laravel evaluates `exists`
 * whether or not `required` already failed for the same attribute). To keep this
 * suite fully DB-free like the rest of it, that side of the matrix is instead
 * established by three DB-free facts taken together: the route carries `super.admin`
 * and not `tenant.admin` (RouteTenantGateCoverageTest), RequireSuperAdmin lets
 * super_admin through in isolation (RequireSuperAdminGateTest), and the gate
 * precedes model binding for this exact route (TenantGatePrecedesRouteBindingTest).
 */
class OrgAdminLoginRouteAuthorizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['supabase.url' => 'https://example.test', 'supabase.anon_key' => 'test-anon-key']);
        // No handlers registered: any call at all fails assertNothingSent() below,
        // rather than silently succeeding against a real network fake.
        Http::fake();
    }

    public static function refusedRoles(): array
    {
        return [
            'an org_admin (the asymmetry this feature is most likely to have gotten wrong)' => ['org_admin', 'org-1'],
            'a driver (should never reach the console at all)' => ['driver', null],
            'a passenger (should never reach the console at all)' => ['passenger', null],
        ];
    }

    #[DataProvider('refusedRoles')]
    public function test_creating_an_org_admin_login_is_refused_for_every_non_super_admin_role(string $role, ?string $orgId, string $why = ''): void
    {
        $this->fillContext($role, $orgId);

        $response = $this->withSession(['supabase_token' => 'test-session-token'])->post('/org-admins/login', [
            'organization_id' => (string) Str::uuid(),
            'full_name' => 'Juan Dela Cruz',
            'username' => 'jsantos',
            'password' => 'a-strong-password',
        ]);

        $response->assertForbidden();
        Http::assertNothingSent();
    }

    #[DataProvider('refusedRoles')]
    public function test_resetting_an_org_admins_password_is_refused_for_every_non_super_admin_role(string $role, ?string $orgId, string $why = ''): void
    {
        $this->fillContext($role, $orgId);

        // A fabricated, non-existent profile id. If the gate ever regressed to run
        // AFTER route-model binding, this would 404 (from a real SELECT) instead of
        // the 403 asserted below — see TenantGatePrecedesRouteBindingTest.
        $response = $this->withSession(['supabase_token' => 'test-session-token'])
            ->put('/org-admins/'.Str::uuid().'/reset-password', [
                'password' => 'a-new-strong-password',
                'password_confirmation' => 'a-new-strong-password',
            ]);

        $response->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_an_unauthenticated_guest_cannot_create_an_org_admin_login(): void
    {
        // No withoutMiddleware() here: AuthenticateSupabaseAdmin runs for real and
        // must refuse an unauthenticated request with a redirect, not a crash.
        $response = $this->post('/org-admins/login', [
            'organization_id' => (string) Str::uuid(),
            'full_name' => 'Juan Dela Cruz',
            'username' => 'jsantos',
            'password' => 'a-strong-password',
        ]);

        $response->assertRedirect(route('login'));
        Http::assertNothingSent();
    }

    public function test_an_unauthenticated_guest_cannot_reset_an_org_admins_password(): void
    {
        $response = $this->put('/org-admins/'.Str::uuid().'/reset-password', [
            'password' => 'a-new-strong-password',
            'password_confirmation' => 'a-new-strong-password',
        ]);

        $response->assertRedirect(route('login'));
        Http::assertNothingSent();
    }

    private function fillContext(string $role, ?string $orgId): void
    {
        $this->withoutMiddleware(AuthenticateSupabaseAdmin::class);

        $ctx = $this->app->make(AdminContext::class);
        $ctx->fill([
            'userId' => (string) Str::uuid(),
            'role' => $role,
            'organizationId' => $orgId,
        ]);
    }
}
