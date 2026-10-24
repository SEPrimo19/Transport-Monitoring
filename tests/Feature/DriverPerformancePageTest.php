<?php

namespace Tests\Feature;

use App\Http\Middleware\AuthenticateSupabaseAdmin;
use App\Support\AdminContext;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The driver performance page, end to end: the real controller, the real scoped models and the
 * real aggregation SQL, over a private in-memory SQLite database with two cooperatives in it.
 * Same approach as CrossTenantIsolationTest, and it never touches the shared Supabase Postgres.
 *
 * It is the only test that proves the numbers, not just the plumbing: that a driver's counts
 * are their own, that another cooperative's driver never appears, and that the degenerate
 * cases (a driver with no name, trips with no timetable, a report with no trip) read honestly.
 */
class DriverPerformancePageTest extends TestCase
{
    private string $orgA;

    private string $orgB;

    private string $originalConnection;

    private string $anaId;

    private string $bobId;

    private string $benId;

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

        Schema::create('organizations', function (Blueprint $b) {
            $b->string('id')->primary();
            $b->string('name')->nullable();
            $b->string('primary_color')->nullable();
            $b->string('secondary_color')->nullable();
            $b->string('logo_url')->nullable();
            $b->string('status')->nullable();
            $b->integer('overspeed_limit_kmh')->nullable();
        });
        Schema::create('profiles', function (Blueprint $b) {
            $b->string('id')->primary();
            $b->string('full_name')->nullable();
            $b->string('role')->nullable();
            $b->string('organization_id')->nullable();
            $b->boolean('is_active')->default(true);
        });
        Schema::create('drivers', function (Blueprint $b) {
            $b->string('profile_id')->primary();
            $b->string('organization_id')->nullable();
            $b->string('employee_code')->nullable();
            $b->boolean('is_active')->default(true);
        });
        Schema::create('routes', function (Blueprint $b) {
            $b->string('id')->primary();
            $b->string('organization_id')->nullable();
            $b->string('code')->nullable();
            $b->string('name')->nullable();
            $b->boolean('is_active')->default(true);
        });
        // The layout's notification bell reads recent reports with their vehicle and route, so
        // a full page render needs these even though the scorecard itself does not.
        Schema::create('vehicles', function (Blueprint $b) {
            $b->string('id')->primary();
            $b->string('organization_id')->nullable();
            $b->string('plate_number')->nullable();
            $b->string('fleet_code')->nullable();
            $b->string('status')->nullable();
            $b->boolean('is_active')->default(true);
        });
        Schema::create('trip_metrics', function (Blueprint $b) {
            $b->string('trip_id')->primary();
            $b->string('organization_id');
            $b->string('driver_profile_id');
            $b->string('vehicle_id')->nullable();
            $b->string('route_id')->nullable();
            $b->string('started_at');
            $b->string('ended_at')->nullable();
            $b->integer('duration_min')->default(0);
            $b->float('distance_km')->nullable();
            $b->float('avg_moving_speed_kmh')->nullable();
            $b->float('max_speed_kmh')->nullable();
            $b->float('overspeed_share')->nullable();
            $b->string('scheduled_departure')->nullable();
            $b->integer('departure_delta_min')->nullable();
            $b->string('punctuality');
            $b->integer('point_count')->default(0);
            $b->string('data_quality')->default('complete');
            $b->string('finalized_at')->nullable();
            $b->string('computed_at')->nullable();
        });
        Schema::create('reports', function (Blueprint $b) {
            $b->string('id')->primary();
            $b->string('organization_id')->nullable();
            $b->string('trip_id')->nullable();
            $b->string('category')->nullable();
            $b->string('status')->nullable();
            $b->string('resolution_outcome')->nullable();
            $b->boolean('is_anonymous')->default(false);
            $b->string('passenger_profile_id')->nullable();
            $b->string('description')->nullable();
            $b->string('vehicle_id')->nullable();
            $b->string('route_id')->nullable();
            $b->string('resolved_at')->nullable();
            $b->string('resolved_by_profile_id')->nullable();
            $b->string('created_at')->nullable();
            $b->string('updated_at')->nullable();
        });

        $this->orgA = (string) Str::uuid();
        $this->orgB = (string) Str::uuid();
        $this->withoutMiddleware(AuthenticateSupabaseAdmin::class);

        DB::table('organizations')->insert([
            ['id' => $this->orgA, 'name' => 'Cooperative A', 'status' => 'active', 'overspeed_limit_kmh' => null],
            ['id' => $this->orgB, 'name' => 'Cooperative B', 'status' => 'active', 'overspeed_limit_kmh' => 60],
        ]);

        $this->anaId = $this->seedDriver($this->orgA, 'Ana Cruz', 'ana');
        $this->bobId = $this->seedDriver($this->orgA, null, 'bob');        // a profile with no name
        $this->benId = $this->seedDriver($this->orgB, 'Ben Reyes', 'ben');
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

    private function seedDriver(string $org, ?string $name, string $code): string
    {
        $id = (string) Str::uuid();
        DB::table('profiles')->insert(['id' => $id, 'full_name' => $name, 'role' => 'driver', 'organization_id' => $org]);
        DB::table('drivers')->insert(['profile_id' => $id, 'organization_id' => $org, 'employee_code' => $code]);

        return $id;
    }

    private function seedTrip(string $org, string $driver, string $punctuality, array $extra = []): string
    {
        $id = (string) Str::uuid();
        DB::table('trip_metrics')->insert($extra + [
            'trip_id' => $id, 'organization_id' => $org, 'driver_profile_id' => $driver,
            'started_at' => now()->subDays(2)->format('Y-m-d H:i:s'),
            'duration_min' => 60, 'distance_km' => 40.0, 'avg_moving_speed_kmh' => 40.0,
            'max_speed_kmh' => 70.0, 'punctuality' => $punctuality, 'computed_at' => now()->format('Y-m-d H:i:s'),
        ]);

        return $id;
    }

    private function seedReport(string $org, ?string $trip, string $category, string $status = 'submitted', ?string $outcome = null): void
    {
        DB::table('reports')->insert([
            'id' => (string) Str::uuid(), 'organization_id' => $org, 'trip_id' => $trip, 'category' => $category,
            'status' => $status, 'resolution_outcome' => $outcome, 'created_at' => now()->format('Y-m-d H:i:s'),
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    private function rows(\Illuminate\Testing\TestResponse $response): array
    {
        return $response->viewData('rows');
    }

    public function test_each_driver_sees_their_own_numbers_and_only_their_own_cooperatives_drivers(): void
    {
        $t1 = $this->seedTrip($this->orgA, $this->anaId, 'on_time');
        $this->seedTrip($this->orgA, $this->anaId, 'on_time');
        $this->seedTrip($this->orgA, $this->anaId, 'late');
        $t4 = $this->seedTrip($this->orgA, $this->bobId, 'no_timetable');
        $this->seedTrip($this->orgA, $this->bobId, 'no_timetable');
        $tb = $this->seedTrip($this->orgB, $this->benId, 'on_time');

        $this->seedReport($this->orgA, $t1, 'driver_behavior', 'resolved', 'upheld');
        $this->seedReport($this->orgA, $t1, 'vehicle_condition');
        $this->seedReport($this->orgA, $t4, 'safety');
        $this->seedReport($this->orgB, $tb, 'driver_behavior');

        $this->actAs('org_admin', $this->orgA);
        $response = $this->get('/analytics/drivers')->assertOk();
        $rows = $this->rows($response);

        $this->assertSame(['Ana Cruz', 'bob'], array_column($rows, 'name'),
            "Cooperative B's driver must not appear, and a nameless profile falls back to the employee code.");

        [$ana, $bob] = $rows;
        $this->assertSame(3, $ana['trips']);
        $this->assertSame(3, $ana['scored']);
        $this->assertSame(66.7, $ana['on_time_pct']);
        $this->assertSame(3.0, $ana['hours'], 'Three 60-minute trips.');
        $this->assertSame(120.0, $ana['distance_km']);
        $this->assertSame(1, $ana['complaints']['total'], 'The vehicle-condition report is not the driver\'s.');
        $this->assertSame(1, $ana['complaints']['upheld']);
        $this->assertSame(0, $ana['safety_related']);

        $this->assertSame(2, $bob['trips']);
        $this->assertSame(0, $bob['scored']);
        $this->assertNull($bob['on_time_pct'], 'No timetable is not the same as never on time.');
        $this->assertSame(2, $bob['no_timetable']);
        $this->assertSame(1, $bob['safety_related']);
        $this->assertSame(0, $bob['complaints']['total']);
    }

    public function test_the_other_cooperatives_complaint_never_reaches_this_scorecard(): void
    {
        $tb = $this->seedTrip($this->orgB, $this->benId, 'on_time');
        $this->seedTrip($this->orgA, $this->anaId, 'on_time');
        $this->seedReport($this->orgB, $tb, 'driver_behavior');

        $this->actAs('org_admin', $this->orgA);
        $rows = $this->rows($this->get('/analytics/drivers')->assertOk());

        $this->assertSame(0, collect($rows)->sum(fn ($r) => $r['complaints']['total']));
    }

    public function test_a_report_with_no_trip_is_counted_as_unattributed_and_given_to_nobody(): void
    {
        $this->seedTrip($this->orgA, $this->anaId, 'on_time');
        $this->seedReport($this->orgA, null, 'driver_behavior');

        $this->actAs('org_admin', $this->orgA);
        $response = $this->get('/analytics/drivers')->assertOk();

        $this->assertSame(1, $response->viewData('unattributed'));
        $this->assertSame(0, collect($this->rows($response))->sum(fn ($r) => $r['complaints']['total']));
    }

    public function test_trips_outside_the_period_are_left_out(): void
    {
        $this->seedTrip($this->orgA, $this->anaId, 'on_time');
        $this->seedTrip($this->orgA, $this->anaId, 'on_time', ['started_at' => now()->subDays(200)->format('Y-m-d H:i:s')]);

        $this->actAs('org_admin', $this->orgA);

        $this->assertSame(1, $this->rows($this->get('/analytics/drivers')->assertOk())[0]['trips']);
        $this->assertSame(2, $this->rows($this->get('/analytics/drivers?from='.now()->subDays(300)->format('Y-m-d').'&to='.now()->format('Y-m-d'))->assertOk())[0]['trips']);
    }

    public function test_the_overspeed_limit_is_this_cooperatives_own_and_empty_means_not_set(): void
    {
        $this->seedTrip($this->orgA, $this->anaId, 'on_time');

        $this->actAs('org_admin', $this->orgA);

        $this->assertNull($this->get('/analytics/drivers')->assertOk()->viewData('overspeedLimit'),
            'Cooperative A has not set a limit; it must not inherit B\'s 60.');
    }

    public function test_the_rendered_page_says_what_it_means(): void
    {
        $t1 = $this->seedTrip($this->orgA, $this->anaId, 'on_time');
        $this->seedTrip($this->orgA, $this->bobId, 'no_timetable');
        $this->seedTrip($this->orgB, $this->benId, 'on_time');
        $this->seedReport($this->orgA, $t1, 'driver_behavior', 'resolved', null);

        $this->actAs('org_admin', $this->orgA);

        $this->get('/analytics/drivers')->assertOk()
            ->assertSee('Ana Cruz')
            ->assertSee('bob')
            ->assertDontSee('Ben Reyes')
            ->assertSee('no timetable')
            ->assertSee('limit not set')
            ->assertSee('outcome not recorded')
            ->assertSee('indicative, not evidence', false);
    }

    // ── the per-driver detail page ──────────────────────────────────────────

    private function seedRoute(string $org, string $code): string
    {
        $id = (string) Str::uuid();
        DB::table('routes')->insert(['id' => $id, 'organization_id' => $org, 'code' => $code, 'name' => "Route $code"]);

        return $id;
    }

    public function test_the_detail_page_shows_that_drivers_routes_trips_and_complaints(): void
    {
        $r1 = $this->seedRoute($this->orgA, 'ALN-CAL');
        $r2 = $this->seedRoute($this->orgA, 'CAL-ALN');
        $t1 = $this->seedTrip($this->orgA, $this->anaId, 'on_time', ['route_id' => $r1]);
        $this->seedTrip($this->orgA, $this->anaId, 'late', ['route_id' => $r2]);
        $this->seedTrip($this->orgA, $this->bobId, 'on_time', ['route_id' => $r1]);
        $this->seedReport($this->orgA, $t1, 'driver_behavior', 'resolved', 'upheld');
        $this->seedReport($this->orgA, $t1, 'vehicle_condition');

        $this->actAs('org_admin', $this->orgA);
        $response = $this->get("/analytics/drivers/{$this->anaId}")->assertOk();

        $this->assertSame(2, $response->viewData('row')['trips'], 'Only this driver trips are counted, not the other driver.');
        $this->assertEqualsCanonicalizing(['ALN-CAL', 'CAL-ALN'], array_column($response->viewData('byRoute'), 'code'));
        $this->assertSame(1, $response->viewData('complaints')->count(),
            'Only the conduct report is listed; a vehicle-condition one is not about the driver.');

        $response->assertSee('Ana Cruz')->assertSee('ALN-CAL')->assertSee('Upheld');
    }

    public function test_the_detail_page_never_names_who_complained(): void
    {
        $t1 = $this->seedTrip($this->orgA, $this->anaId, 'on_time');
        $reporter = (string) Str::uuid();
        DB::table('profiles')->insert(['id' => $reporter, 'full_name' => 'Zelda Complainant', 'role' => 'passenger']);
        DB::table('reports')->insert([
            'id' => (string) Str::uuid(), 'organization_id' => $this->orgA, 'trip_id' => $t1,
            'category' => 'driver_behavior', 'status' => 'submitted', 'passenger_profile_id' => $reporter,
            'created_at' => now()->format('Y-m-d H:i:s'),
        ]);

        $this->actAs('org_admin', $this->orgA);

        $this->get("/analytics/drivers/{$this->anaId}")->assertOk()->assertDontSee('Zelda Complainant');
    }

    public function test_another_cooperatives_driver_is_not_found(): void
    {
        $this->seedTrip($this->orgB, $this->benId, 'on_time');

        $this->actAs('org_admin', $this->orgA);

        $this->get("/analytics/drivers/{$this->benId}")->assertNotFound();
    }

    public function test_a_driver_with_no_trips_still_has_a_page(): void
    {
        $this->actAs('org_admin', $this->orgA);

        $response = $this->get("/analytics/drivers/{$this->bobId}")->assertOk();

        $this->assertSame(0, $response->viewData('row')['trips']);
        $response->assertSee('bob');
    }

    public function test_the_index_links_each_driver_to_their_page(): void
    {
        $this->seedTrip($this->orgA, $this->anaId, 'on_time');

        $this->actAs('org_admin', $this->orgA);

        $this->get('/analytics/drivers')->assertOk()->assertSee("/analytics/drivers/{$this->anaId}", false);
    }

    // ── the CSV export ──────────────────────────────────────────────────────

    /** @return array<int, array<int, string>> the CSV as rows, header first */
    private function csv(\Illuminate\Testing\TestResponse $response): array
    {
        $body = $response->streamedContent();
        $body = preg_replace('/^\xEF\xBB\xBF/', '', $body);

        return array_map('str_getcsv', array_filter(explode("\n", str_replace("\r\n", "\n", $body)), 'strlen'));
    }

    public function test_the_export_is_a_csv_of_the_same_rows_as_the_page(): void
    {
        $this->seedTrip($this->orgA, $this->anaId, 'on_time');
        $this->seedTrip($this->orgA, $this->anaId, 'late');
        $this->seedTrip($this->orgB, $this->benId, 'on_time');

        $this->actAs('org_admin', $this->orgA);
        $response = $this->get('/analytics/drivers/export')->assertOk();

        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('driver-performance-', $response->headers->get('Content-Disposition'));

        $rows = $this->csv($response);
        $header = array_shift($rows);

        $this->assertSame('Driver', $header[0]);
        $this->assertSame(['Ana Cruz', 'bob'], array_column($rows, 0), 'The same two drivers as the page, nobody from cooperative B.');
        $this->assertSame('2', $rows[0][array_search('Trips', $header, true)]);
    }

    public function test_a_driver_named_like_a_formula_cannot_run_in_a_spreadsheet(): void
    {
        $evil = $this->seedDriver($this->orgA, '=HYPERLINK("http://evil","click")', 'evil');
        $this->seedTrip($this->orgA, $evil, 'on_time');

        $this->actAs('org_admin', $this->orgA);
        $rows = $this->csv($this->get('/analytics/drivers/export')->assertOk());

        $names = array_column($rows, 0);
        $this->assertContains("'=HYPERLINK(\"http://evil\",\"click\")", $names);
        $this->assertNotContains('=HYPERLINK("http://evil","click")', $names);
    }

    public function test_a_missing_value_is_an_empty_cell_not_a_zero(): void
    {
        // Bob has no scored trips: on-time must be blank, never 0.
        $this->seedTrip($this->orgA, $this->bobId, 'no_timetable');

        $this->actAs('org_admin', $this->orgA);
        $rows = $this->csv($this->get('/analytics/drivers/export')->assertOk());
        $header = array_shift($rows);
        $bob = collect($rows)->firstWhere(0, 'bob');

        $this->assertSame('', $bob[array_search('On time %', $header, true)]);
    }

    public function test_the_export_route_is_not_swallowed_by_the_driver_route(): void
    {
        $this->actAs('org_admin', $this->orgA);

        // If {driver} matched first, "export" would be looked up as a driver id and 404.
        $this->get('/analytics/drivers/export')->assertOk()->assertHeader('Content-Type');
    }

    public function test_a_super_admin_cannot_export_a_cooperatives_drivers(): void
    {
        $this->actAs('super_admin', null);

        $this->get('/analytics/drivers/export')->assertForbidden();
    }

    public function test_the_index_offers_the_export_and_print(): void
    {
        $this->seedTrip($this->orgA, $this->anaId, 'on_time');

        $this->actAs('org_admin', $this->orgA);

        $this->get('/analytics/drivers')->assertOk()
            ->assertSee('/analytics/drivers/export', false)
            ->assertSee('Print');
    }

    public function test_a_driver_id_that_is_not_a_uuid_is_a_404_not_a_server_error(): void
    {
        $this->actAs('org_admin', $this->orgA);

        $this->get('/analytics/drivers/not-a-uuid')->assertNotFound();
    }

    public function test_the_driver_page_prints_without_the_console_chrome(): void
    {
        $this->seedTrip($this->orgA, $this->anaId, 'on_time');
        $this->actAs('org_admin', $this->orgA);

        $this->get('/analytics/drivers/'.$this->anaId)->assertOk()
            ->assertSee('media="print"', false)
            ->assertSee('Print');
    }

    public function test_a_super_admin_is_refused(): void
    {
        $this->actAs('super_admin', null);

        $this->get('/analytics/drivers')->assertForbidden();
    }
}
