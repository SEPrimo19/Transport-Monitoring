<?php

namespace Tests\Feature;

use App\Http\Middleware\AuthenticateSupabaseAdmin;
use App\Models\Vehicle;
use App\Support\AdminContext;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Behavioural cross-tenant isolation: an org_admin of cooperative A must not be able to
 * read, edit, triage or delete cooperative B rows by guessing a UUID, and must not see
 * them in list screens.
 *
 * The existing suite only checks that the tenant gate is PRESENT on each route
 * (RouteTenantGateCoverageTest) and ordered before binding. Nothing proved the
 * OrganizationScope actually filters route-model binding end to end.
 *
 * Runs against a private in-memory SQLite database with throwaway tables, so it never
 * touches the shared Supabase Postgres. The default connection is switched BEFORE any
 * model is queried and restored in tearDown. AuthenticateSupabaseAdmin (JWT + JWKS +
 * live profiles row) is bypassed and AdminContext is filled directly, as
 * DriverLoginOrgScopeTest does.
 */
class CrossTenantIsolationTest extends TestCase
{
    private string $orgA;

    private string $orgB;

    private string $originalConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalConnection = config('database.default');
        config([
            'database.default' => 'qa_sqlite',
            'database.connections.qa_sqlite' => [
                'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false,
            ],
        ]);
        DB::purge('qa_sqlite');

        foreach (['vehicles', 'reports', 'trips', 'routes', 'terminals', 'drivers', 'assignments', 'trip_schedules'] as $t) {
            Schema::create($t, function (Blueprint $b) use ($t) {
                $b->string($t === 'drivers' ? 'profile_id' : 'id')->primary();
                $b->string('organization_id')->nullable();
                $b->string('status')->nullable();
                $b->string('plate_number')->nullable();
                $b->string('code')->nullable();
                $b->string('name')->nullable();
                $b->string('driver_profile_id')->nullable();
                $b->string('route_id')->nullable();
                $b->string('vehicle_id')->nullable();
                $b->boolean('is_active')->default(true);
                $b->string('resolved_at')->nullable();
                $b->string('resolved_by_profile_id')->nullable();
                // Mirrors migration 046 (reports.resolution_outcome).
                $b->string('resolution_outcome')->nullable();
                $b->string('created_at')->nullable();
                $b->string('updated_at')->nullable();
            });
        }

        $this->orgA = (string) Str::uuid();
        $this->orgB = (string) Str::uuid();

        $this->withoutMiddleware(AuthenticateSupabaseAdmin::class);
    }

    protected function tearDown(): void
    {
        config(['database.default' => $this->originalConnection]);
        DB::purge('qa_sqlite');
        parent::tearDown();
    }

    private function actAs(string $role, ?string $org): void
    {
        $this->app->make(AdminContext::class)->fill([
            'userId' => (string) Str::uuid(), 'role' => $role, 'organizationId' => $org,
        ]);
    }

    private function insertRow(string $table, string $org, array $extra = []): string
    {
        $id = (string) Str::uuid();
        DB::table($table)->insert([
            ($table === 'drivers' ? 'profile_id' : 'id') => $id,
            'organization_id' => $org,
        ] + $extra);

        return $id;
    }

    public function test_foreign_org_rows_are_not_addressable_on_any_tenant_route(): void
    {
        $this->actAs('org_admin', $this->orgA);

        $foreign = [
            'vehicles' => $this->insertRow('vehicles', $this->orgB, ['plate_number' => 'B-1']),
            'reports' => $this->insertRow('reports', $this->orgB, ['status' => 'submitted']),
            'trips' => $this->insertRow('trips', $this->orgB, ['status' => 'active']),
            'routes' => $this->insertRow('routes', $this->orgB, ['code' => 'B-R']),
            'terminals' => $this->insertRow('terminals', $this->orgB, ['code' => 'B-T']),
            'drivers' => $this->insertRow('drivers', $this->orgB),
            'assignments' => $this->insertRow('assignments', $this->orgB),
            'schedules' => $this->insertRow('trip_schedules', $this->orgB),
        ];

        $probes = [
            ['GET', "/vehicles/{$foreign['vehicles']}/edit"],
            ['PUT', "/vehicles/{$foreign['vehicles']}"],
            ['DELETE', "/vehicles/{$foreign['vehicles']}"],
            ['GET', "/reports/{$foreign['reports']}"],
            ['PUT', "/reports/{$foreign['reports']}"],
            ['GET', "/trips/{$foreign['trips']}"],
            ['GET', "/routes/{$foreign['routes']}/edit"],
            ['PUT', "/routes/{$foreign['routes']}"],
            ['DELETE', "/routes/{$foreign['routes']}"],
            ['GET', "/routes/{$foreign['routes']}/geometry"],
            ['PUT', "/routes/{$foreign['routes']}/geometry"],
            ['DELETE', "/routes/{$foreign['routes']}/geometry"],
            ['GET', "/terminals/{$foreign['terminals']}/edit"],
            ['DELETE', "/terminals/{$foreign['terminals']}"],
            ['GET', "/drivers/{$foreign['drivers']}/edit"],
            ['DELETE', "/drivers/{$foreign['drivers']}"],
            ['PUT', "/drivers/{$foreign['drivers']}/reset-password"],
            ['GET', "/assignments/{$foreign['assignments']}/edit"],
            ['DELETE', "/assignments/{$foreign['assignments']}"],
            ['GET', "/schedules/{$foreign['schedules']}/edit"],
            ['DELETE', "/schedules/{$foreign['schedules']}"],
        ];

        foreach ($probes as [$method, $uri]) {
            $this->call($method, $uri, ['status' => 'resolved'])
                ->assertNotFound("{$method} {$uri} must 404 for another cooperative row");
        }

        // Nothing was mutated or removed by the probes.
        $this->assertSame(1, DB::table('vehicles')->count());
        $this->assertSame('submitted', DB::table('reports')->value('status'));
        $this->assertNull(DB::table('reports')->value('resolved_at'));
        $this->assertSame(1, DB::table('routes')->count());
        $this->assertSame(1, DB::table('terminals')->count());
        $this->assertSame(1, DB::table('drivers')->count());
        $this->assertSame(1, DB::table('assignments')->count());
        $this->assertSame(1, DB::table('trip_schedules')->count());
    }

    public function test_an_org_admin_can_still_reach_their_own_row(): void
    {
        // Guards against the 404 test above passing only because EVERYTHING 404s.
        $this->actAs('org_admin', $this->orgA);
        $report = $this->insertRow('reports', $this->orgA, ['status' => 'submitted']);

        $this->put("/reports/{$report}", ['status' => 'resolved', 'resolution_outcome' => 'upheld'])->assertRedirect();

        $this->assertSame('resolved', DB::table('reports')->where('id', $report)->value('status'));
        $this->assertNotNull(DB::table('reports')->where('id', $report)->value('resolved_at'));
        $this->assertSame('upheld', DB::table('reports')->where('id', $report)->value('resolution_outcome'));
    }

    public function test_vehicle_query_contains_only_own_cooperatives_rows(): void
    {
        $this->actAs('org_admin', $this->orgA);
        $this->insertRow('vehicles', $this->orgA, ['plate_number' => 'A-1']);
        $this->insertRow('vehicles', $this->orgB, ['plate_number' => 'B-1']);

        $this->assertSame(['A-1'], Vehicle::query()->pluck('plate_number')->all());
    }

    public function test_org_admin_with_no_organization_sees_nothing_and_is_refused(): void
    {
        $this->actAs('org_admin', null);
        $this->insertRow('vehicles', $this->orgB, ['plate_number' => 'B-1']);

        $this->get('/vehicles')->assertForbidden();
        $this->assertSame([], Vehicle::query()->pluck('plate_number')->all());
    }

    public function test_a_super_admin_is_refused_every_tenant_data_screen(): void
    {
        $this->actAs('super_admin', null);
        $report = $this->insertRow('reports', $this->orgB, ['status' => 'submitted']);

        foreach (['/vehicles', '/drivers', '/routes', '/terminals', '/schedules', '/assignments', '/trips',
            '/reports', "/reports/{$report}", '/operations', '/operations/data', '/notifications/pending',
            '/organization'] as $uri) {
            $this->get($uri)->assertForbidden();
        }
        $this->put("/reports/{$report}", ['status' => 'resolved'])->assertForbidden();
        $this->assertSame('submitted', DB::table('reports')->value('status'));
    }

    public function test_assignment_store_rejects_another_cooperatives_driver_vehicle_and_route(): void
    {
        $this->actAs('org_admin', $this->orgA);
        $driver = $this->insertRow('drivers', $this->orgB, ['is_active' => true]);
        $vehicle = $this->insertRow('vehicles', $this->orgB, ['status' => 'active']);
        $route = $this->insertRow('routes', $this->orgB, ['is_active' => true]);

        $this->post('/assignments', [
            'driver_profile_id' => $driver, 'vehicle_id' => $vehicle, 'route_id' => $route,
            'starts_at' => '2026-10-01 08:00', 'ends_at' => '2026-10-02 08:00', 'is_active' => '1',
        ])->assertSessionHasErrors(['driver_profile_id', 'vehicle_id', 'route_id']);

        $this->assertSame(0, DB::table('assignments')->count());
    }

    public function test_assignment_store_rejects_end_before_start(): void
    {
        $this->actAs('org_admin', $this->orgA);
        $driver = $this->insertRow('drivers', $this->orgA, ['is_active' => true]);
        $vehicle = $this->insertRow('vehicles', $this->orgA, ['status' => 'active']);
        $route = $this->insertRow('routes', $this->orgA, ['is_active' => true]);

        $this->post('/assignments', [
            'driver_profile_id' => $driver, 'vehicle_id' => $vehicle, 'route_id' => $route,
            'starts_at' => '2026-10-02 08:00', 'ends_at' => '2026-10-01 08:00', 'is_active' => '1',
        ])->assertSessionHasErrors(['ends_at']);
    }

    public function test_assignment_store_rejects_an_inactive_vehicle_even_in_own_org(): void
    {
        $this->actAs('org_admin', $this->orgA);
        $driver = $this->insertRow('drivers', $this->orgA, ['is_active' => true]);
        $vehicle = $this->insertRow('vehicles', $this->orgA, ['status' => 'maintenance']);
        $route = $this->insertRow('routes', $this->orgA, ['is_active' => true]);

        $this->post('/assignments', [
            'driver_profile_id' => $driver, 'vehicle_id' => $vehicle, 'route_id' => $route,
            'starts_at' => '2026-10-01 08:00', 'ends_at' => '2026-10-02 08:00', 'is_active' => '1',
        ])->assertSessionHasErrors(['vehicle_id']);
    }

    public function test_report_status_update_rejects_unknown_status(): void
    {
        $this->actAs('org_admin', $this->orgA);
        $report = $this->insertRow('reports', $this->orgA, ['status' => 'submitted']);

        $this->put("/reports/{$report}", ['status' => 'banana'])->assertSessionHasErrors('status');
        $this->put("/reports/{$report}", ['status' => ['resolved']])->assertSessionHasErrors('status');
        $this->put("/reports/{$report}", [])->assertSessionHasErrors('status');
        $this->assertSame('submitted', DB::table('reports')->value('status'));
    }

    public function test_reopening_a_resolved_report_clears_resolution_fields(): void
    {
        $this->actAs('org_admin', $this->orgA);
        $report = $this->insertRow('reports', $this->orgA, [
            'status' => 'resolved', 'resolved_at' => '2026-10-01', 'resolved_by_profile_id' => 'someone',
            'resolution_outcome' => 'upheld',
        ]);

        $this->put("/reports/{$report}", ['status' => 'reviewing'])->assertRedirect();

        $this->assertNull(DB::table('reports')->where('id', $report)->value('resolved_at'));
        $this->assertNull(DB::table('reports')->where('id', $report)->value('resolved_by_profile_id'));
        $this->assertNull(DB::table('reports')->where('id', $report)->value('resolution_outcome'),
            'A reopened report must not keep a verdict; migration 046 would refuse it.');
    }

    public function test_resolving_a_report_without_an_outcome_is_refused_and_changes_nothing(): void
    {
        // The point of the outcome: "resolved" alone must never be able to read as upheld.
        $this->actAs('org_admin', $this->orgA);
        $report = $this->insertRow('reports', $this->orgA, ['status' => 'reviewing']);

        $this->put("/reports/{$report}", ['status' => 'resolved'])->assertSessionHasErrors('resolution_outcome');

        $this->assertSame('reviewing', DB::table('reports')->where('id', $report)->value('status'));
        $this->assertNull(DB::table('reports')->where('id', $report)->value('resolved_at'));
    }

    public function test_dismissing_a_report_never_stores_a_stale_outcome(): void
    {
        $this->actAs('org_admin', $this->orgA);
        $report = $this->insertRow('reports', $this->orgA, ['status' => 'reviewing']);

        // The radios are hidden, not removed, so this can arrive with any status.
        $this->put("/reports/{$report}", ['status' => 'dismissed', 'resolution_outcome' => 'upheld'])->assertRedirect();

        $this->assertSame('dismissed', DB::table('reports')->where('id', $report)->value('status'));
        $this->assertNull(DB::table('reports')->where('id', $report)->value('resolution_outcome'));
    }

    public function test_an_org_admin_is_refused_every_platform_route(): void
    {
        $this->actAs('org_admin', $this->orgA);

        foreach (['/organizations', '/organizations/create', '/onboarding', '/org-admins', '/org-admins/create'] as $uri) {
            $this->get($uri)->assertForbidden();
        }
        $this->post('/organizations', ['name' => 'x'])->assertForbidden();
        $this->post('/onboarding', [])->assertForbidden();
        $this->post('/org-admins', [])->assertForbidden();
        $this->post('/org-admins/login', [])->assertForbidden();
        $this->delete('/org-admins/'.Str::uuid())->assertForbidden();
        $this->put('/org-admins/'.Str::uuid().'/reset-password', [])->assertForbidden();
    }
}
