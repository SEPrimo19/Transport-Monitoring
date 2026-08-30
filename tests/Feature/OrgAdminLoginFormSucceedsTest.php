<?php

namespace Tests\Feature;

use App\Http\Middleware\AuthenticateSupabaseAdmin;
use App\Support\AdminContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The success path of the org-admin creation form, end to end through the real route.
 *
 * WHY THIS DID NOT EXIST, AND WHY THAT MATTERED
 * The controller body had never been reached by any test. Every case in
 * {@see OrgAdminLoginRouteAuthorizationTest} is REFUSED, and refusal happens in
 * RequireSuperAdmin before the controller is constructed; the allowed side was left out
 * on purpose to keep that suite DB-free, because CreateOrgAdminLoginRequest carries an
 * unconditional Rule::exists on organization_id.
 *
 * So the whole 248-test suite passed while `storeLogin()` was dead. Its signature
 * type-hints OrgAdminAccountProvisioner, the class was never imported, and an unimported
 * name resolves against the controller's OWN namespace — so the container threw
 * ReflectionException before a single line of the method ran. Creating a cooperative's
 * first administrator returned a 500 from the feature's first commit until it was fixed.
 * Checking the git history, the import was absent from 7bc3877 onward: that form has
 * never once completed.
 *
 * WHAT THIS TEST DOES AND DOES NOT TOUCH
 * The Edge Function is faked, so NO account is minted and nothing reaches the live
 * account service. What is exercised for real: routing, RequireSuperAdmin, form-request
 * validation, controller dependency resolution (the thing that was broken), the
 * provisioner call itself, and the redirect the admin lands on.
 *
 * It reads one real organization id, because Rule::exists cannot be satisfied otherwise
 * and faking the validator would stop this being an end-to-end test of the form. That is
 * a SELECT and nothing more — consistent with the eleven other suites here that read live
 * rows, and with this suite's standing rule that reads are tolerable and writes are not.
 * The test skips rather than fails when no organization is reachable, so a developer
 * without database access still gets a green suite.
 */
class OrgAdminLoginFormSucceedsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['supabase.url' => 'https://example.test', 'supabase.anon_key' => 'test-anon-key']);
    }

    public function test_a_super_admin_can_create_an_org_admin_login_through_the_form(): void
    {
        $organizationId = $this->anExistingOrganizationId();

        Http::fake([
            '*/functions/v1/driver-account' => Http::response([
                'profile_id' => (string) Str::uuid(),
                'email' => 'jsantos@admins.amoectrack.invalid',
            ], 200),
        ]);

        $this->actAsSuperAdmin();

        $response = $this->withSession(['supabase_token' => 'test-session-token'])
            ->post('/org-admins/login', [
                'organization_id' => $organizationId,
                'full_name' => 'Juan Dela Cruz',
                'username' => 'jsantos',
                'password' => 'a-strong-password',
            ]);

        // A ReflectionException from the container surfaces as a 500, which is exactly
        // what this route did before the import was added.
        $response->assertRedirect(route('org-admins.index'));
        $response->assertSessionHas('status');
        $response->assertSessionMissing('error');

        $status = (string) session('status');
        $this->assertStringContainsString('Juan Dela Cruz', $status);
        $this->assertStringContainsString('jsantos@admins.amoectrack.invalid', $status);
    }

    public function test_the_form_sends_the_super_admin_only_action_with_the_admins_own_token(): void
    {
        $organizationId = $this->anExistingOrganizationId();

        Http::fake([
            '*/functions/v1/driver-account' => Http::response([
                'profile_id' => (string) Str::uuid(),
                'email' => 'jsantos@admins.amoectrack.invalid',
            ], 200),
        ]);

        $this->actAsSuperAdmin();

        $this->withSession(['supabase_token' => 'test-session-token'])
            ->post('/org-admins/login', [
                'organization_id' => $organizationId,
                'full_name' => 'Juan Dela Cruz',
                'username' => 'jsantos',
                'password' => 'a-strong-password',
            ]);

        Http::assertSent(function ($request) use ($organizationId) {
            $body = $request->data();

            return str_contains($request->url(), '/functions/v1/driver-account')
                // The super_admin-only action, not the driver-tier `create`.
                && ($body['action'] ?? null) === 'create_org_admin'
                && ($body['organization_id'] ?? null) === $organizationId
                && ($body['username'] ?? null) === 'jsantos'
                // The signed-in admin's own session token, never service_role.
                && $request->hasHeader('Authorization', 'Bearer test-session-token');
        });
    }

    /**
     * A rejection from the account service must come back as a re-rendered form, not a
     * crash — and the password must not be flashed back into the session, which is
     * driven to the database.
     */
    public function test_a_taken_username_returns_to_the_form_without_flashing_the_password(): void
    {
        $organizationId = $this->anExistingOrganizationId();

        Http::fake([
            '*/functions/v1/driver-account' => Http::response(
                ['error' => 'That username is already taken.'],
                409
            ),
        ]);

        $this->actAsSuperAdmin();

        $response = $this->withSession(['supabase_token' => 'test-session-token'])
            ->post('/org-admins/login', [
                'organization_id' => $organizationId,
                'full_name' => 'Juan Dela Cruz',
                'username' => 'jsantos',
                'password' => 'a-strong-password',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertStringContainsString('already taken', (string) session('error'));

        $flashedInput = session('_old_input', []);
        $this->assertArrayNotHasKey('password', $flashedInput, 'the chosen password was flashed into the session');
        $this->assertSame('jsantos', $flashedInput['username'] ?? null, 'the rest of the form should come back');
    }

    private function actAsSuperAdmin(): void
    {
        // AuthenticateSupabaseAdmin verifies a real GoTrue JWT against JWKS and reads a
        // live profiles row; AdminContext is filled directly instead, exactly as that
        // middleware would on success. RequireSuperAdmin is NOT bypassed.
        $this->withoutMiddleware(AuthenticateSupabaseAdmin::class);

        $this->app->make(AdminContext::class)->fill([
            'userId' => (string) Str::uuid(),
            'role' => 'super_admin',
            'organizationId' => null,
        ]);
    }

    private function anExistingOrganizationId(): string
    {
        try {
            $id = DB::table('organizations')->value('id');
        } catch (\Throwable $e) {
            $this->markTestSkipped('No database reachable for the organization_id exists rule.');
        }

        if (! $id) {
            $this->markTestSkipped('No organization rows to satisfy the organization_id exists rule.');
        }

        return (string) $id;
    }
}
