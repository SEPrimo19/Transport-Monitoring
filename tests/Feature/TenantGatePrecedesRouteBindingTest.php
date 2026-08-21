<?php

namespace Tests\Feature;

use App\Http\Middleware\AuthenticateSupabaseAdmin;
use App\Http\Middleware\RequireSuperAdmin;
use App\Http\Middleware\RequireTenantAdmin;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Pins the fix for the route-model-binding-before-the-gate hole: `bootstrap/app.php`
 * only prepended {@see AuthenticateSupabaseAdmin} ahead of
 * {@see SubstituteBindings} in the priority list, so {@see RequireTenantAdmin}
 * and {@see RequireSuperAdmin} ran LAST — after route-model binding
 * had already resolved `{report}`/`{profile}`/etc.
 *
 * For a bound tenant route like reports.show, that meant `SubstituteBindings` called
 * `resolveRouteBinding` before the gate ran. `OrganizationScope` applies no filter for
 * a super_admin (mirroring migration 016), so a foreign cooperative's report row was
 * SELECTed and hydrated before the request was refused — cross-tenant processing of
 * exactly the data RequireTenantAdmin exists to keep a platform admin out of, plus a
 * 403-vs-404 existence oracle (missing id 404s at binding, foreign-tenant id 403s at
 * the gate).
 *
 * This resolves the router's fully sorted, priority-ordered middleware stack — not
 * the raw `$route->middleware()` alias list RouteTenantGateCoverageTest checks — so it
 * catches an ordering regression that a "does the route carry tenant.admin at all"
 * check cannot.
 *
 * Resolving Illuminate\Contracts\Http\Kernel (without dispatching a request) is
 * required first: TestCase::createApplication() only bootstraps the CONSOLE kernel,
 * so the HTTP kernel's middleware groups/priority list are never synced to the router
 * until something resolves the HTTP kernel contract. Without this, gatherRouteMiddleware()
 * returns the group name "web" unexpanded and an empty priority list, which would make
 * this test pass for the wrong reason.
 */
class TenantGatePrecedesRouteBindingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make(Kernel::class);
    }

    public function test_tenant_admin_gate_runs_before_route_model_binding(): void
    {
        $this->assertGateBeforeBinding('reports.show', 'App\Http\Middleware\RequireTenantAdmin');
    }

    public function test_super_admin_gate_runs_before_route_model_binding(): void
    {
        $this->assertGateBeforeBinding('org-admins.destroy', 'App\Http\Middleware\RequireSuperAdmin');
    }

    /**
     * org-admins.reset-password is a bound sub-resource route ({profile}) declared
     * apart from Route::resource('org-admins', ...), added alongside
     * create_org_admin/reset_org_admin_password. The same hole this file exists to
     * pin — a foreign/irrelevant row resolved before the tier gate refuses the
     * request — applies here too: without this, an org_admin's crafted request could
     * cause {profile} to be SELECTed before RequireSuperAdmin ever runs.
     */
    public function test_super_admin_gate_runs_before_route_model_binding_for_org_admin_password_reset(): void
    {
        $this->assertGateBeforeBinding('org-admins.reset-password', 'App\Http\Middleware\RequireSuperAdmin');
    }

    private function assertGateBeforeBinding(string $routeName, string $gate): void
    {
        $route = Route::getRoutes()->getByName($routeName);
        $this->assertNotNull($route, "No route is registered with the name [{$routeName}].");

        $resolved = app('router')->gatherRouteMiddleware($route);

        $gateIndex = array_search($gate, $resolved, true);
        $bindingIndex = array_search(SubstituteBindings::class, $resolved, true);

        $this->assertNotFalse($gateIndex,
            "[{$gate}] did not appear in the resolved middleware stack for [{$routeName}]: [".implode(', ', $resolved).'].');
        $this->assertNotFalse($bindingIndex,
            "SubstituteBindings did not appear in the resolved middleware stack for [{$routeName}]: [".implode(', ', $resolved).'].');

        $this->assertLessThan(
            $bindingIndex,
            $gateIndex,
            "[{$gate}] must run BEFORE SubstituteBindings for [{$routeName}], or route-model binding ".
            'resolves the bound model before the tier gate refuses the request. Resolved order: ['.
            implode(', ', $resolved).'].'
        );
    }
}
