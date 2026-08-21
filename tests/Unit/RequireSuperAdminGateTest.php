<?php

namespace Tests\Unit;

use App\Http\Middleware\RequireSuperAdmin;
use App\Support\AdminContext;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * The mirror image of {@see TenantAdminGateTest}: the boundary that keeps an
 * org_admin OUT of platform-tier screens (Organizations, Org Admins, Onboarding).
 *
 * An org_admin managing another cooperative's existence, suspending it, or
 * appointing/demoting its administrators is the escalation this gate exists to
 * prevent — the reverse of the privacy case for RequireTenantAdmin, but just as
 * damaging: it is a cooperative reaching outside its own tenant boundary rather
 * than a platform operator reaching into one.
 *
 * Extends the framework TestCase (not PHPUnit's) because abort() needs a booted
 * application to resolve the exception. No database is touched — the middleware
 * only reads AdminContext.
 */
class RequireSuperAdminGateTest extends TestCase
{
    private function runGate(?string $role): Response
    {
        $ctx = new AdminContext;
        $ctx->userId = 'user-1';
        $ctx->role = $role;
        app()->instance(AdminContext::class, $ctx);

        return (new RequireSuperAdmin)->handle(
            Request::create('/organizations', 'GET'),
            fn () => new Response('reached', 200)
        );
    }

    private function assertRefused(?string $role, string $because): void
    {
        try {
            $this->runGate($role);
            $this->fail("{$because}: the request reached the controller but should have been refused.");
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode(), $because);
        }
    }

    public function test_a_platform_admin_is_allowed_through(): void
    {
        $response = $this->runGate('super_admin');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('reached', $response->getContent());
    }

    public function test_a_cooperative_admin_is_refused(): void
    {
        // The whole point of the gate. An org_admin reaching /organizations could
        // create, edit, or suspend a cooperative it does not run, or (via
        // /org-admins) promote itself to administer one it was never appointed to.
        $this->assertRefused('org_admin', 'org_admin must not reach platform-tier tenant management');
    }

    public function test_an_unfilled_context_is_refused(): void
    {
        $this->assertRefused(null, 'no role means no access');
    }

    public function test_a_driver_or_passenger_role_is_refused(): void
    {
        // These never reach the console at all (supabase.admin denies them first),
        // but the gate must not depend on that upstream check alone.
        $this->assertRefused('driver', 'a driver is not a console user');
        $this->assertRefused('passenger', 'a passenger is not a console user');
    }
}
