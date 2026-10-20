<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Driver;
use App\Models\Report;
use App\Models\Route;
use App\Models\Terminal;
use App\Models\Trip;
use App\Models\TripSchedule;
use App\Models\Vehicle;
use Tests\TestCase;

/**
 * The global search is the one screen that reads every tenant table at once, so the
 * thing that keeps one cooperative's data away from another's is worth pinning rather
 * than trusting to review.
 *
 * Three layers, and each gets an assertion here:
 *
 *   1. the route is gated — covered by RouteTenantGateCoverageTest, which now names
 *      `search` in its tenant-only list;
 *   2. every model the controller queries carries BelongsToOrganization, so
 *      OrganizationScope adds `organization_id = <this admin's org>` and falls closed
 *      to `1 = 0` with no context;
 *   3. the controller reaches the database ONLY through those models — one raw
 *      DB::table() would silently opt out of layer 2.
 *
 * Deliberately DB-free. This suite has no test database and `.env` points at the LIVE
 * Supabase instance the mobile app writes to (see TestCase), so proving isolation by
 * creating a second organization's rows is exactly the test that must not be written
 * here. Reflection and source inspection prove the same guarantees without a
 * connection.
 */
class SearchTenantIsolationTest extends TestCase
{
    /** Every model SearchController queries. */
    private const SEARCHED_MODELS = [
        Vehicle::class,
        Driver::class,
        Terminal::class,
        Route::class,
        TripSchedule::class,
        Assignment::class,
        Trip::class,
        Report::class,
    ];

    public function test_every_model_the_search_reads_is_organization_scoped(): void
    {
        foreach (self::SEARCHED_MODELS as $model) {
            $this->assertContains(
                BelongsToOrganization::class,
                class_uses_recursive($model),
                $model.' is searched by SearchController but is not org-scoped, so a '.
                'term would match rows belonging to another cooperative.'
            );
        }
    }

    public function test_the_search_controller_never_queries_a_raw_table(): void
    {
        // Comments stripped first: the controller's own docblock states this rule in
        // prose, and matching that would be the test grading its own explanation.
        $source = $this->codeOf(app_path('Http/Controllers/SearchController.php'));

        // DB::table() and the query-builder facade bypass Eloquent, and with it the
        // global scope that is the whole isolation guarantee on this screen.
        foreach (['DB::table(', 'DB::select(', 'DB::raw(', '->fromQuery(', 'whereRaw('] as $escape) {
            $this->assertStringNotContainsString(
                $escape,
                $source,
                'SearchController uses '.$escape.', which steps outside Eloquent and '.
                'therefore outside OrganizationScope. Query through a scoped model.'
            );
        }
    }

    /** Source with comments removed, so prose about a rule cannot trip the rule. */
    private function codeOf(string $path): string
    {
        $code = '';
        foreach (token_get_all(file_get_contents($path)) as $token) {
            if (is_array($token)) {
                if ($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT) {
                    continue;
                }
                $code .= $token[1];
            } else {
                $code .= $token;
            }
        }

        return $code;
    }

    public function test_the_scope_falls_closed_without_an_organization(): void
    {
        // Reading the scope's own source rather than booting a request: the point is
        // that the no-context branch is a refusal, not an absent filter.
        $scope = file_get_contents(app_path('Models/Scopes/OrganizationScope.php'));

        $this->assertStringContainsString('1 = 0', $scope,
            'OrganizationScope must fail closed when there is no organization context.');
        $this->assertStringContainsString("organization_id", $scope,
            'OrganizationScope must filter on organization_id.');
    }

    public function test_search_results_link_only_to_tenant_gated_screens(): void
    {
        $source = $this->codeOf(app_path('Http/Controllers/SearchController.php'));

        // Every destination a result can link to is itself behind tenant.admin, so a
        // result can never be a door into something the gate would otherwise refuse.
        preg_match_all("/route\('([a-z.\-]+)'/", $source, $m);
        $named = array_values(array_unique($m[1]));

        $this->assertNotEmpty($named, 'Expected the controller to build named routes.');

        $tenantOnly = array_merge(...array_values(RouteTenantGateCoverageTest::tenantOnlyRoutes()));

        foreach ($named as $name) {
            $this->assertContains($name, $tenantOnly,
                "SearchController links to '{$name}', which is not in the tenant-only ".
                'route list — a result would point at a screen outside the gate.');
        }
    }
}
