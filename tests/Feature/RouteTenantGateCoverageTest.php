<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Enumerates every named admin-console route and pins which gate it actually
 * carries, straight from the router's own middleware resolution
 * (`Route::getRoutes()->getByName(...)->middleware()`) rather than re-reading
 * routes/web.php by eye.
 *
 * This is the automated version of `php artisan route:list --columns=uri,name,middleware`:
 * it fails the build the moment a route is added outside its intended group, or a
 * sub-resource route (drivers.login.store, drivers.reset-password,
 * routes.geometry.*) is declared apart from its resource and forgets the gate.
 *
 * DB-free: route registration is pure PHP (routes/web.php has no closures — see the
 * comment there about route caching — and defining a route touches nothing but the
 * router). No request is dispatched here, so no controller and therefore no
 * database connection is ever reached.
 */
class RouteTenantGateCoverageTest extends TestCase
{
    /**
     * Routes inside the `supabase.admin` group that are deliberately reachable by
     * BOTH tiers and so carry neither `tenant.admin` nor `super.admin`. Adding a name
     * here must be a reviewable, explicit act — see test_every_admin_route_declares_a_tier.
     *
     * - dashboard: aggregate platform-health counts, safe for either tier (see
     *   DashboardController's docblock).
     * - account.edit / account.update: an admin's own account, not tenant data.
     * - activity.index: account-administration history. Both tiers open it and the
     *   SCOPE differs rather than the access — an org_admin reads their own
     *   cooperative's events, a super_admin reads platform-tier ones (organization_id
     *   IS NULL), per migration 037. A tier gate would have to pick one and lock the
     *   other out, so the line is drawn by AccountAdminEvent::scopeVisibleTo instead
     *   and pinned by AdminActivityVisibilityTest.
     */
    private const TIER_FREE = ['dashboard', 'account.edit', 'account.update', 'activity.index'];

    /**
     * Every tenant-data route: org_admin only. A super_admin reaching any of these
     * would be reading passenger reports, driver PII, or trip/location history that
     * belongs to a cooperative it does not administer.
     */
    public static function tenantOnlyRoutes(): array
    {
        return [
            ['operations.index'],
            ['operations.data'],
            ['vehicles.index'], ['vehicles.store'], ['vehicles.create'],
            ['vehicles.update'], ['vehicles.destroy'], ['vehicles.edit'],
            ['drivers.index'], ['drivers.store'], ['drivers.create'],
            ['drivers.update'], ['drivers.destroy'], ['drivers.edit'],
            // Sub-resource routes declared apart from Route::resource('drivers', ...)
            // — the review flagged these by name as the likeliest place to miss the gate.
            ['drivers.login.store'],
            ['drivers.reset-password'],
            ['terminals.index'], ['terminals.store'], ['terminals.create'],
            ['terminals.update'], ['terminals.destroy'], ['terminals.edit'],
            ['routes.index'], ['routes.store'], ['routes.create'],
            ['routes.update'], ['routes.destroy'], ['routes.edit'],
            // Sub-resource routes declared apart from Route::resource('routes', ...).
            ['routes.geometry.edit'], ['routes.geometry.update'], ['routes.geometry.destroy'],
            ['schedules.index'], ['schedules.store'], ['schedules.create'],
            ['schedules.update'], ['schedules.destroy'], ['schedules.edit'],
            ['assignments.index'], ['assignments.store'], ['assignments.create'],
            ['assignments.update'], ['assignments.destroy'], ['assignments.edit'],
            ['trips.index'], ['trips.show'],
            ['reports.index'], ['reports.show'], ['reports.update'],
            ['notifications.pending'],
            // Reads every tenant table at once, so it belongs behind the same gate as
            // all eight screens it searches.
            ['search'],
            // Per-driver performance figures and complaint counts: HR data about named people.
            ['analytics.drivers.index'],
            ['analytics.drivers.show'],
            ['analytics.drivers.export'],
            ['report-center.drivers'],
            ['report-center.index'],
            ['report-center.trips'],
            ['report-center.trips.export'],
            // A cooperative's OWN profile — org_admin edits it; super_admin uses
            // Organizations instead. Nested in its own group per routes/web.php;
            // the thing most likely to be broken by that nesting.
            ['organization.edit'], ['organization.update'],
        ];
    }

    /**
     * Platform-tier routes: super_admin only. An org_admin reaching any of these
     * could create/edit tenants or promote itself (or anyone) to administer another
     * cooperative.
     */
    public static function superAdminOnlyRoutes(): array
    {
        return [
            ['organizations.index'], ['organizations.store'], ['organizations.create'],
            ['organizations.update'], ['organizations.edit'],
            ['onboarding.create'], ['onboarding.store'],
            ['org-admins.index'], ['org-admins.store'], ['org-admins.create'], ['org-admins.destroy'],
            // Sub-resource routes declared apart from the org-admins resource, mirroring
            // drivers.login.store / drivers.reset-password above — the review flagged
            // these by name as the likeliest place to miss the gate (create_org_admin /
            // reset_org_admin_password are SUPER_ADMIN ONLY, unlike the driver
            // equivalents, which both tiers may reach).
            ['org-admins.login.store'], ['org-admins.reset-password'],
        ];
    }

    /**
     * Reachable by ANY signed-in admin tier, tenant-scope-free: aggregate dashboard
     * counts and an admin's own account. Neither the `tenant.admin` nor the
     * `super.admin` gate belongs here — either one would be over-blocking.
     */
    public static function eitherAdminRoutes(): array
    {
        return [
            ['dashboard'],
            ['account.edit'], ['account.update'],
        ];
    }

    /** Reachable without any session at all. */
    public static function guestRoutes(): array
    {
        return [
            ['login'], ['login.attempt'], ['logout'],
        ];
    }

    #[DataProvider('tenantOnlyRoutes')]
    public function test_tenant_data_route_carries_the_tenant_admin_gate(string $name): void
    {
        $middleware = $this->middlewareFor($name);

        $this->assertContains(
            'tenant.admin',
            $middleware,
            "Route [{$name}] is tenant data and MUST carry the tenant.admin gate. ".
            'Its actual middleware stack is: ['.implode(', ', $middleware).']. '.
            'Without it, a super_admin can read or write this cooperative-scoped resource — '.
            'exactly the leak this middleware was built to close.'
        );

        $this->assertNotContains(
            'super.admin',
            $middleware,
            "Route [{$name}] must NOT also require super.admin — that would lock every ".
            'org_admin out of a route they are supposed to use day to day.'
        );
    }

    #[DataProvider('superAdminOnlyRoutes')]
    public function test_platform_route_carries_the_super_admin_gate(string $name): void
    {
        $middleware = $this->middlewareFor($name);

        $this->assertContains(
            'super.admin',
            $middleware,
            "Route [{$name}] is platform-tier (tenant management) and MUST carry the ".
            'super.admin gate. Its actual middleware stack is: ['.implode(', ', $middleware).']. '.
            'Without it, an org_admin could manage or suspend cooperatives it does not run.'
        );

        $this->assertNotContains(
            'tenant.admin',
            $middleware,
            "Route [{$name}] must NOT also require tenant.admin — a super_admin (who ".
            'is refused by that gate) would be locked out of managing tenants entirely.'
        );
    }

    #[DataProvider('eitherAdminRoutes')]
    public function test_shared_admin_route_carries_neither_tenant_nor_super_gate(string $name): void
    {
        $middleware = $this->middlewareFor($name);

        $this->assertContains('supabase.admin', $middleware,
            "Route [{$name}] must still require an authenticated admin session.");

        $this->assertNotContains('tenant.admin', $middleware,
            "Route [{$name}] must be reachable by a super_admin too — tenant.admin would over-block it.");

        $this->assertNotContains('super.admin', $middleware,
            "Route [{$name}] must be reachable by an org_admin too — super.admin would over-block it.");
    }

    #[DataProvider('guestRoutes')]
    public function test_guest_route_carries_no_admin_gate(string $name): void
    {
        $middleware = $this->middlewareFor($name);

        $this->assertNotContains('supabase.admin', $middleware,
            "Route [{$name}] must be reachable without a session.");
        $this->assertNotContains('tenant.admin', $middleware);
        $this->assertNotContains('super.admin', $middleware);
    }

    /**
     * Every tenant-data AND platform route must sit inside the `supabase.admin`
     * group in the first place — tenant.admin/super.admin alone still abort()
     * correctly for an unauthenticated caller (AdminContext is just empty), but a
     * route escaping the outer group would skip token verification entirely for
     * any request that never reaches the tenant/super check's early-return paths.
     */
    #[DataProvider('tenantOnlyRoutes')]
    #[DataProvider('superAdminOnlyRoutes')]
    public function test_gated_route_also_requires_an_authenticated_admin_session(string $name): void
    {
        $this->assertContains('supabase.admin', $this->middlewareFor($name),
            "Route [{$name}] must sit inside the supabase.admin group.");
    }

    /**
     * The other tests here enumerate route names by hand: they pin what exists
     * TODAY, but a route added tomorrow inside `supabase.admin` and outside both
     * `tenant.admin` and `super.admin` appears in no provider above and the suite
     * stays green — 130 of these 180 tests come from this one file, so that green
     * is a large number that would not grow when the surface does.
     *
     * This test inverts the check: it walks the ACTUAL registered route table (not
     * a hand-written list) and fails if any `supabase.admin` route lacks a tier gate,
     * unless its name is explicitly in TIER_FREE. Adding a name to TIER_FREE is the
     * reviewable act "deliberately visible to both tiers" should be — the failure
     * message tells the next person to do exactly that instead of silently shipping
     * an ungated route.
     */
    public function test_every_admin_route_declares_a_tier(): void
    {
        $ungated = [];

        foreach (Route::getRoutes() as $route) {
            $middleware = $route->middleware();

            if (! in_array('supabase.admin', $middleware, true)) {
                continue;
            }

            if (in_array($route->getName(), self::TIER_FREE, true)) {
                continue;
            }

            if (! array_intersect(['tenant.admin', 'super.admin'], $middleware)) {
                $ungated[] = $route->getName() ?? $route->uri();
            }
        }

        $this->assertSame([], $ungated,
            'New admin routes must declare a tier: add tenant.admin or super.admin, '.
            'or add the name to TIER_FREE with a reason.');
    }

    private function middlewareFor(string $routeName): array
    {
        $route = Route::getRoutes()->getByName($routeName);

        $this->assertNotNull($route, "No route is registered with the name [{$routeName}]. ".
            'Either the route was renamed/removed, or this test needs updating — check routes/web.php.');

        return $route->middleware();
    }
}
