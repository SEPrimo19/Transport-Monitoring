<?php

namespace Tests\Unit;

use App\Support\AdminContext;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

/**
 * The dashboard's two top stat cards (vehicles transmitting now / reports awaiting
 * review) show an aggregate COUNT that is fine for either admin tier — see
 * DashboardController::metrics(), where every subquery stays subject to
 * OrganizationScope and a super_admin's count is a legitimate platform-wide total,
 * not tenant data.
 *
 * The call to action is a different matter: both cards link to routes gated by
 * `tenant.admin` (operations.index, reports.index), which always refuses a
 * super_admin. So the count must render for both tiers, but the `<a>` wrapper and
 * the "open Live Ops" / "triage now" wording must render ONLY for org_admin, with a
 * plain descriptive label taking their place for a super_admin. The same principle
 * covers the KPI tiles and the "Manage vehicles" link further down the page.
 *
 * This renders the real `dashboard` view for each tier and asserts on the HTML
 * (mirroring NotificationFeedGatingTest) rather than scanning the raw Blade source.
 * The source-scanning version of this file matched on prose ('transmitting now</div>')
 * and only checked the FIRST occurrence of a marker — which is how the "Manage
 * vehicles" link (dashboard.blade.php:176) shipped unguarded despite this suite
 * being green. Rendering with fake tiles/counts needs no controller and no database.
 */
class DashboardStatCardGatingTest extends TestCase
{
    /** The CTA link is the tenant-data call to action; org_admin only. */
    public function test_stat_card_links_render_only_for_org_admin(): void
    {
        $orgHtml = $this->renderDashboard('org_admin');
        $superHtml = $this->renderDashboard('super_admin');

        $operationsHref = 'href="'.route('operations.index').'"';
        $this->assertStringContainsString($operationsHref, $orgHtml);
        $this->assertStringNotContainsString($operationsHref, $superHtml);

        $reportsHref = 'href="'.route('reports.index', ['status' => 'submitted']).'"';
        $this->assertStringContainsString($reportsHref, $orgHtml);
        $this->assertStringNotContainsString($reportsHref, $superHtml);
    }

    public function test_stat_card_cta_wording_renders_only_for_org_admin(): void
    {
        $orgHtml = $this->renderDashboard('org_admin');
        $superHtml = $this->renderDashboard('super_admin');

        $this->assertStringContainsString('open Live Ops', $orgHtml);
        $this->assertStringNotContainsString('open Live Ops', $superHtml);

        $this->assertStringContainsString('triage now', $orgHtml);
        $this->assertStringNotContainsString('triage now', $superHtml);
    }

    /**
     * The chevron-right glyph signals "this is clickable, go on" — leaving it on the
     * super_admin card once the link is gone would be a dangling affordance for a
     * dead end. It must only render alongside the actual link (one per stat card).
     */
    public function test_stat_card_chevron_affordance_renders_only_for_org_admin(): void
    {
        $orgHtml = $this->renderDashboard('org_admin');
        $superHtml = $this->renderDashboard('super_admin');

        $this->assertSame(2, substr_count($orgHtml, 'bi-chevron-right ms-auto text-muted'),
            'Expected exactly 2 chevron affordances for org_admin (one per stat card).');
        $this->assertSame(0, substr_count($superHtml, 'bi-chevron-right ms-auto text-muted'),
            'A super_admin must not see a chevron pointing at a link that is not there.');
    }

    /** The counts themselves must appear on both sides of the split, not just one. */
    public function test_stat_card_counts_render_for_both_tiers(): void
    {
        $orgHtml = $this->renderDashboard('org_admin', liveNow: 7, reportsPending: 4);
        $superHtml = $this->renderDashboard('super_admin', liveNow: 7, reportsPending: 4);

        $this->assertStringContainsString(number_format(7), $orgHtml);
        $this->assertStringContainsString(number_format(7), $superHtml);
        $this->assertStringContainsString(number_format(4), $orgHtml);
        $this->assertStringContainsString(number_format(4), $superHtml);
    }

    /**
     * Finding 2: five of seven KPI tiles were unconditional `<a href>`s into
     * tenant.admin-gated routes — a guaranteed 403 for a super_admin. The href is
     * now omitted for that tier (DashboardController::index) and the view must
     * render the tile unlinked rather than pointing at a dead end.
     */
    public function test_kpi_tile_is_a_link_for_org_admin_and_plain_for_super_admin(): void
    {
        $tile = ['label' => 'Vehicles', 'value' => 12, 'sub' => '10 active', 'icon' => 'bus-front'];

        $orgHtml = $this->renderDashboard('org_admin', tiles: [$tile + ['href' => route('vehicles.index')]]);
        $superHtml = $this->renderDashboard('super_admin', tiles: [$tile + ['href' => null]]);

        $vehiclesHref = 'href="'.route('vehicles.index').'"';
        $this->assertStringContainsString($vehiclesHref, $orgHtml);
        $this->assertStringNotContainsString($vehiclesHref, $superHtml);

        // The label/value/sub still render either way — only the click-through is gone.
        $this->assertStringContainsString('Vehicles', $superHtml);
        $this->assertStringContainsString(number_format(12), $superHtml);
    }

    /**
     * dashboard.blade.php:176 ("Manage vehicles" in the Fleet status card) is the
     * link a first-occurrence source scanner missed. Gated on isOrgAdmin() directly.
     */
    public function test_manage_vehicles_link_renders_only_for_org_admin(): void
    {
        $orgHtml = $this->renderDashboard('org_admin');
        $superHtml = $this->renderDashboard('super_admin');

        $this->assertStringContainsString('Manage vehicles', $orgHtml);
        $this->assertStringNotContainsString('Manage vehicles', $superHtml);
    }

    private function renderDashboard(string $role, array $tiles = [], int $liveNow = 0, int $reportsPending = 0): string
    {
        $ctx = $this->app->make(AdminContext::class);
        $ctx->fill(['userId' => 'user-1', 'role' => $role, 'organizationId' => null]);

        return View::make('dashboard', [
            'tiles' => $tiles,
            'liveNow' => $liveNow,
            'reportsPending' => $reportsPending,
            'vehicleStatus' => collect(['active' => 0, 'maintenance' => 0, 'inactive' => 0]),
            'recentReports' => collect(),
        ])->render();
    }
}
