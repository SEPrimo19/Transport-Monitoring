<?php

namespace Tests\Unit;

use App\Support\AdminContext;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

/**
 * `resources/views/layouts/app.blade.php` (the sidebar) and
 * `resources/views/dashboard.blade.php` (the Recent Reports card) both had their
 * `@if`/`@endif` nesting hand-edited to add the tenant/platform split. Unlike a
 * PHP `if`, a stray or swapped Blade `@endif` still compiles — it just attaches to
 * the wrong block. `assertBalanced` below still catches that with a raw source
 * scan (no app boot needed for it).
 *
 * Whether a link is actually reachable for a tier is checked differently: by
 * rendering the view for each role and asserting on the resulting HTML, mirroring
 * {@see NotificationFeedGatingTest}. A source-scanning approach (matching Blade
 * condition text) has two blind spots a render does not: `@if ($ctx->isOrgAdmin()
 * || $ctx->isSuperAdmin())` would satisfy a `str_contains(..., 'isOrgAdmin')`
 * check while reopening the hole, and a scanner that only inspects the FIRST
 * occurrence of a marker misses a second, unguarded one later in the same file —
 * which is exactly how the dashboard's "Manage vehicles" link
 * (dashboard.blade.php:176) slipped past the review this test suite is meant to
 * pin against.
 *
 * DB-free like NotificationFeedGatingTest: AdminContext is filled directly, and
 * organizationId is left null so the org-chrome composer's `Organization::find()`
 * branch (gated on organizationId being set) never runs.
 */
class AdminConsoleBladeGatingTest extends TestCase
{
    private const LAYOUT = __DIR__.'/../../resources/views/layouts/app.blade.php';

    private const DASHBOARD = __DIR__.'/../../resources/views/dashboard.blade.php';

    public function test_layout_blade_conditionals_are_balanced(): void
    {
        $this->assertBalanced(self::LAYOUT);
    }

    public function test_dashboard_blade_conditionals_are_balanced(): void
    {
        $this->assertBalanced(self::DASHBOARD);
    }

    /**
     * Sidebar tenant-data links (Fleet / Scheduling / Support / Settings section)
     * must be reachable only for org_admin. The routes themselves refuse a
     * super_admin regardless (tenant.admin), so this is about not presenting a
     * link that is a guaranteed dead end for that tier — the tell that the same
     * nesting slip could just as easily un-nest something that DOES leak.
     */
    public function test_sidebar_tenant_links_render_only_for_org_admin(): void
    {
        $tenantRoutes = [
            'operations.index', 'vehicles.index', 'drivers.index', 'terminals.index',
            'routes.index', 'schedules.index', 'assignments.index', 'trips.index',
            'reports.index', 'organization.edit',
        ];

        $superHtml = $this->renderLayout('super_admin');
        $orgHtml = $this->renderLayout('org_admin');

        foreach ($tenantRoutes as $name) {
            $href = 'href="'.route($name).'"';

            $this->assertStringNotContainsString($href, $superHtml,
                "Sidebar must NOT link to [{$name}] for a super_admin — it is behind tenant.admin and always 403s.");
            $this->assertStringContainsString($href, $orgHtml,
                "Sidebar must link to [{$name}] for an org_admin.");
        }
    }

    /** The Platform section (Organizations / Org Admins / Onboard co-op) is super_admin only. */
    public function test_sidebar_platform_links_render_only_for_super_admin(): void
    {
        $platformRoutes = ['organizations.index', 'org-admins.index', 'onboarding.create'];

        $superHtml = $this->renderLayout('super_admin');
        $orgHtml = $this->renderLayout('org_admin');

        foreach ($platformRoutes as $name) {
            $href = 'href="'.route($name).'"';

            $this->assertStringContainsString($href, $superHtml,
                "Sidebar must link to [{$name}] for a super_admin.");
            $this->assertStringNotContainsString($href, $orgHtml,
                "Sidebar must NOT link to [{$name}] for an org_admin — it is behind super.admin and always 403s.");
        }
    }

    /**
     * Dashboard / My account / Sign out must render for EITHER tier. Over-blocking
     * one of these with a stray role conditional would lock a whole tier out of its
     * own landing page or account menu — as much a regression as under-blocking
     * tenant data.
     */
    public function test_universally_reachable_links_render_for_both_tiers(): void
    {
        foreach (['super_admin', 'org_admin'] as $role) {
            $html = $this->renderLayout($role);

            $this->assertStringContainsString('href="'.route('dashboard').'"', $html,
                "dashboard link missing from the sidebar for {$role}.");
            $this->assertStringContainsString('href="'.route('account.edit').'"', $html,
                "account.edit link missing from the user menu for {$role}.");
            $this->assertStringContainsString('action="'.route('logout').'"', $html,
                "logout form action missing for {$role}.");
        }
    }

    /**
     * The dashboard's Recent Reports card carries a passenger's words about a named
     * driver, with photo/video evidence one click away — exactly the tenant data
     * RequireTenantAdmin exists to keep a super_admin from reading. The controller
     * already sends an empty collection for a super_admin (DashboardController::index),
     * but the view must ALSO refuse to render the card — belt and suspenders, per the
     * same reasoning as the route gate itself.
     */
    public function test_recent_reports_card_renders_only_for_org_admin(): void
    {
        $orgHtml = $this->renderDashboard('org_admin');
        $superHtml = $this->renderDashboard('super_admin');

        $this->assertStringContainsString('Recent reports', $orgHtml);
        $this->assertStringNotContainsString('Recent reports', $superHtml);

        $viewAllHref = 'href="'.route('reports.index').'"';
        $this->assertStringContainsString($viewAllHref, $orgHtml);
        $this->assertStringNotContainsString($viewAllHref, $superHtml);
    }

    private function renderLayout(string $role): string
    {
        $ctx = $this->app->make(AdminContext::class);
        $ctx->fill(['userId' => 'user-1', 'role' => $role, 'organizationId' => null]);

        return View::make('layouts.app')->render();
    }

    private function renderDashboard(string $role): string
    {
        $ctx = $this->app->make(AdminContext::class);
        $ctx->fill(['userId' => 'user-1', 'role' => $role, 'organizationId' => null]);

        return View::make('dashboard', [
            'tiles' => [],
            'liveNow' => 0,
            'reportsPending' => 0,
            'vehicleStatus' => collect(['active' => 0, 'maintenance' => 0, 'inactive' => 0]),
            'recentReports' => collect(),
        ])->render();
    }

    private function assertBalanced(string $file): void
    {
        $source = file_get_contents($file);
        $this->assertNotFalse($source, "Could not read {$file}");

        $opens = preg_match_all('/@if\s*\(/', $source);
        $closes = preg_match_all('/@endif\b/', $source);
        $this->assertSame($opens, $closes,
            basename($file).": found {$opens} @if(...) but {$closes} @endif — the blocks do not balance.");

        $foreachOpens = preg_match_all('/@foreach\s*\(/', $source);
        $foreachCloses = preg_match_all('/@endforeach\b/', $source);
        $this->assertSame($foreachOpens, $foreachCloses,
            basename($file).': @foreach/@endforeach do not balance.');

        $forelseOpens = preg_match_all('/@forelse\s*\(/', $source);
        $forelseCloses = preg_match_all('/@endforelse\b/', $source);
        $this->assertSame($forelseOpens, $forelseCloses,
            basename($file).': @forelse/@endforelse do not balance.');
    }
}
