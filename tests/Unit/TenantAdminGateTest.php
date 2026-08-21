<?php

namespace Tests\Unit;

use App\Http\Middleware\RequireTenantAdmin;
use App\Support\AdminContext;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * The boundary between the platform tier and a cooperative's own data.
 *
 * A super_admin creates and suspends cooperatives and appoints their administrators. It
 * has no business inside one. Passenger misconduct reports name a driver and can carry
 * photo or video evidence; driver records hold licence and phone numbers; trips are
 * location history about identifiable people. A passenger consented to their own
 * cooperative reviewing a report, not to a platform operator reading it.
 *
 * Before this middleware, OrganizationScope applied NO filter for a super_admin, so every
 * screen showed every cooperative's rows. Hiding the sidebar links alone would have been
 * decoration: the routes still answered, and a bookmark still worked.
 *
 * The test that matters most is the first one. It is the only one asserting a REFUSAL of
 * the most privileged role in the system, which is exactly the kind of check that gets
 * "simplified" later by someone who reads super_admin as "can do everything".
 *
 * Extends the framework TestCase rather than PHPUnit's: abort() needs a booted
 * application to resolve. No database is touched, since the middleware only reads
 * AdminContext.
 */
class TenantAdminGateTest extends TestCase
{
    private function runGate(?string $role, ?string $orgId): Response
    {
        $ctx = new AdminContext;
        $ctx->userId = 'user-1';
        $ctx->role = $role;
        $ctx->organizationId = $orgId;
        app()->instance(AdminContext::class, $ctx);

        return (new RequireTenantAdmin)->handle(
            Request::create('/reports', 'GET'),
            fn () => new Response('reached', 200)
        );
    }

    private function assertRefused(?string $role, ?string $orgId, string $because): void
    {
        try {
            $this->runGate($role, $orgId);
            $this->fail("{$because}: the request reached the controller but should have been refused.");
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode(), $because);
        }
    }

    public function test_a_platform_admin_is_refused_tenant_data(): void
    {
        // The whole point of the middleware. A super_admin reaching /reports would be
        // reading passengers' complaints across every cooperative on the platform.
        $this->assertRefused('super_admin', null, 'super_admin must not reach tenant data');
    }

    public function test_a_platform_admin_with_an_organization_is_still_refused(): void
    {
        // Guards a subtle hole: a super_admin who also happens to carry an organization_id
        // would otherwise look like a legitimate org_admin to a naive check.
        $this->assertRefused('super_admin', 'org-1', 'super_admin must be refused regardless of org');
    }

    public function test_a_cooperative_admin_is_allowed_through(): void
    {
        $response = $this->runGate('org_admin', 'org-1');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('reached', $response->getContent());
    }

    public function test_an_org_admin_without_an_organization_is_refused(): void
    {
        // Fails closed. There is nothing to scope to, and an empty screen would read as
        // missing data rather than as a misconfigured account.
        $this->assertRefused('org_admin', null, 'an org_admin with no organization has nothing to scope to');
    }

    public function test_an_unfilled_context_is_refused(): void
    {
        $this->assertRefused(null, null, 'no role means no access');
    }

    public function test_a_driver_or_passenger_role_is_refused(): void
    {
        // These never reach the console at all, but the gate must not depend on that.
        $this->assertRefused('driver', 'org-1', 'a driver is not a console user');
        $this->assertRefused('passenger', 'org-1', 'a passenger is not a console user');
    }
}
