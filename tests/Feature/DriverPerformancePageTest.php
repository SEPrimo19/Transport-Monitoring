<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Support\AdminContext;
use Tests\Concerns\TwoCooperativeFixture;
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
    use TwoCooperativeFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFixture();
    }

    protected function tearDown(): void
    {
        $this->tearDownFixture();
        parent::tearDown();
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

    public function test_overspeed_only_averages_trips_computed_against_the_current_limit(): void
    {
        // Cooperative B's limit is 60. A trip summarised when the limit was 80 counted a different
        // set of readings, so it must not be averaged under a label that says 60.
        $this->seedTrip($this->orgB, $this->benId, 'on_time', ['overspeed_share' => 0.2, 'overspeed_limit_kmh_used' => 60]);
        $this->seedTrip($this->orgB, $this->benId, 'on_time', ['overspeed_share' => 0.9, 'overspeed_limit_kmh_used' => 80]);

        $this->actAs('org_admin', $this->orgB);
        $rows = $this->rows($this->get('/analytics/drivers')->assertOk());
        $ben = collect($rows)->firstWhere('driver_id', $this->benId);

        $this->assertEquals(0.2, $ben['overspeed_share']);
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

    public function test_the_index_offers_the_export(): void
    {
        $this->seedTrip($this->orgA, $this->anaId, 'on_time');

        $this->actAs('org_admin', $this->orgA);

        $this->get('/analytics/drivers')->assertOk()
            ->assertSee('/analytics/drivers/export', false);
    }

    public function test_a_driver_id_that_is_not_a_uuid_is_a_404_not_a_server_error(): void
    {
        $this->actAs('org_admin', $this->orgA);

        $this->get('/analytics/drivers/not-a-uuid')->assertNotFound();
    }

    public function test_an_unusable_range_says_so_on_the_page(): void
    {
        $this->actAs('org_admin', $this->orgA);

        $this->get('/analytics/drivers?from=2026-10-20&to=2026-10-01')->assertOk()->assertSee('last 30 days');
    }

    // ── the printable report ────────────────────────────────────────────────

    public function test_the_report_uses_the_report_layout_and_names_the_cooperative_and_period(): void
    {
        $this->seedTrip($this->orgA, $this->anaId, 'on_time');
        $this->actAs('org_admin', $this->orgA);

        $this->get('/report-center/drivers')->assertOk()
            ->assertSee('Cooperative A')
            ->assertSee('Generated by')
            ->assertSee('How these numbers are calculated')
            ->assertDontSee('bootstrap-icons', false);
    }

    public function test_an_empty_period_says_no_data_and_does_not_error(): void
    {
        $this->actAs('org_admin', $this->orgA);

        $this->get('/report-center/drivers')->assertOk()->assertSee('No data for this period');
    }

    public function test_on_time_is_a_dash_when_no_trip_was_scored(): void
    {
        $this->seedTrip($this->orgA, $this->anaId, 'no_timetable');
        $this->seedTrip($this->orgA, $this->anaId, 'no_timetable');
        $this->actAs('org_admin', $this->orgA);

        $response = $this->get('/report-center/drivers')->assertOk();

        $this->assertNull($response->viewData('figures')['on_time_pct']);
        $response->assertDontSee('0.0%')->assertDontSee('>0%<', false);
    }

    public function test_the_report_never_shows_another_cooperatives_drivers(): void
    {
        $this->seedTrip($this->orgA, $this->anaId, 'on_time');
        $this->seedTrip($this->orgB, $this->benId, 'on_time');
        $this->actAs('org_admin', $this->orgA);

        $this->get('/report-center/drivers')->assertOk()
            ->assertSee('Ana Cruz')
            ->assertDontSee('Ben Reyes');
    }

    public function test_a_name_with_html_is_escaped_on_the_report(): void
    {
        $evil = $this->seedDriver($this->orgA, '<b>x</b> & y', 'evil');
        $this->seedTrip($this->orgA, $evil, 'on_time');
        $this->actAs('org_admin', $this->orgA);

        $this->get('/report-center/drivers')->assertOk()
            ->assertSee('&lt;b&gt;x&lt;/b&gt; &amp; y', false)
            ->assertDontSee('<b>x</b>', false);
    }

    public function test_two_drivers_with_the_same_name_are_two_rows_with_their_codes(): void
    {
        $a = $this->seedDriver($this->orgA, 'Bea Francisco', 'bea');
        $b = $this->seedDriver($this->orgA, 'Bea Francisco', '0530');
        $this->seedTrip($this->orgA, $a, 'on_time');
        $this->seedTrip($this->orgA, $b, 'late');
        $this->actAs('org_admin', $this->orgA);

        $response = $this->get('/report-center/drivers')->assertOk();
        $names = array_column($this->rows($response), 'name');

        $this->assertSame(2, count(array_keys($names, 'Bea Francisco', true)));
        $response->assertSee('0530')->assertSee('bea');
    }

    public function test_a_deleted_route_with_trips_shows_a_dash_for_its_code(): void
    {
        $this->seedTrip($this->orgA, $this->anaId, 'on_time', ['route_id' => (string) Str::uuid()]);
        $this->actAs('org_admin', $this->orgA);

        $response = $this->get('/report-center/drivers')->assertOk();

        $this->assertSame('—', $response->viewData('perRoute')[0]['route_code']);
    }

    public function test_the_per_route_section_lists_each_route_a_driver_worked(): void
    {
        $r1 = $this->seedRoute($this->orgA, 'ALN-CAL');
        $r2 = $this->seedRoute($this->orgA, 'ALN-CAT');
        $this->seedTrip($this->orgA, $this->anaId, 'on_time', ['route_id' => $r1]);
        $this->seedTrip($this->orgA, $this->anaId, 'late', ['route_id' => $r2]);
        $this->actAs('org_admin', $this->orgA);

        $response = $this->get('/report-center/drivers')->assertOk();

        $this->assertSame(['ALN-CAL', 'ALN-CAT'], array_column($response->viewData('perRoute'), 'route_code'));
    }

    public function test_the_report_offers_the_csv_export_and_print(): void
    {
        $this->seedTrip($this->orgA, $this->anaId, 'on_time');
        $this->actAs('org_admin', $this->orgA);

        $this->get('/report-center/drivers')->assertOk()
            ->assertSee('/analytics/drivers/export', false)
            ->assertSee('Print');
    }

    public function test_an_uppercase_route_id_is_still_labelled_as_that_route(): void
    {
        $route = $this->seedRoute($this->orgA, 'ALN-CAL');
        $this->seedTrip($this->orgA, $this->anaId, 'on_time', ['route_id' => $route]);
        $this->actAs('org_admin', $this->orgA);

        $this->get('/report-center/drivers?route='.strtoupper($route))->assertOk()
            ->assertSee('ALN-CAL')
            ->assertDontSee('All routes');
    }

    public function test_a_single_driver_report_lists_only_that_driver(): void
    {
        $cy = $this->seedDriver($this->orgA, 'Cy Dela', 'cy');
        $this->seedTrip($this->orgA, $this->anaId, 'on_time');
        $this->seedTrip($this->orgA, $cy, 'late');
        $this->actAs('org_admin', $this->orgA);

        $response = $this->get('/report-center/drivers?driver='.$this->anaId)->assertOk();

        $response->assertSee('Ana Cruz')->assertDontSee('Cy Dela');
        $this->assertCount(1, $this->rows($response));
    }

    public function test_a_single_driver_report_for_another_cooperatives_driver_is_a_404(): void
    {
        $this->seedTrip($this->orgB, $this->benId, 'on_time');
        $this->actAs('org_admin', $this->orgA);

        $this->get('/report-center/drivers?driver='.$this->benId)->assertNotFound();
        $this->get('/report-center/drivers?driver=not-a-uuid')->assertNotFound();
    }

    public function test_the_driver_page_generates_a_report_for_that_driver(): void
    {
        $this->seedTrip($this->orgA, $this->anaId, 'on_time');
        $this->actAs('org_admin', $this->orgA);

        $this->get('/analytics/drivers/'.$this->anaId)->assertOk()
            ->assertSee('driver='.$this->anaId, false);
    }

    public function test_an_unusable_range_says_so_on_the_report(): void
    {
        $this->actAs('org_admin', $this->orgA);

        $this->get('/report-center/drivers?from=2026-10-20&to=2026-10-01')->assertOk()->assertSee('last 30 days');
    }

    public function test_a_super_admin_cannot_open_the_report(): void
    {
        $this->actAs('super_admin', null);

        $this->get('/report-center/drivers')->assertForbidden();
    }

    // ── the Report center hub ───────────────────────────────────────────────

    public function test_the_hub_lists_the_driver_report_with_generate_and_export_links(): void
    {
        $this->actAs('org_admin', $this->orgA);

        $this->get('/report-center')->assertOk()
            ->assertSee('Driver performance')
            ->assertSee('/report-center/drivers', false)
            ->assertSee('/analytics/drivers/export', false);
    }

    public function test_the_hub_carries_the_chosen_period_into_the_links(): void
    {
        $this->actAs('org_admin', $this->orgA);

        $this->get('/report-center?from=2026-10-01&to=2026-10-20')->assertOk()
            ->assertSee('from=2026-10-01', false)
            ->assertSee('to=2026-10-20', false);
    }

    public function test_a_super_admin_is_refused_on_the_hub(): void
    {
        $this->actAs('super_admin', null);

        $this->get('/report-center')->assertForbidden();
    }

    public function test_the_hub_route_is_not_swallowed_by_a_later_route(): void
    {
        $this->actAs('org_admin', $this->orgA);

        $this->get('/report-center')->assertOk();
        $this->get('/report-center/drivers')->assertOk();
    }

    public function test_the_drivers_screens_offer_generate_report_and_no_longer_a_print_button(): void
    {
        $this->seedTrip($this->orgA, $this->anaId, 'on_time');
        $this->actAs('org_admin', $this->orgA);

        $this->get('/analytics/drivers')->assertOk()
            ->assertSee('Generate report')
            ->assertSee('/report-center/drivers', false)
            ->assertDontSee('window.print()', false);

        $this->get('/analytics/drivers/'.$this->anaId)->assertOk()
            ->assertSee('Generate report')
            ->assertDontSee('window.print()', false);
    }

    public function test_detail_with_a_trip_limit_returns_only_that_many_recent_trips(): void
    {
        for ($i = 0; $i < 12; $i++) {
            $this->seedTrip($this->orgA, $this->anaId, 'on_time', ['started_at' => now()->subDays(2)->addMinutes($i)->format('Y-m-d H:i:s')]);
        }
        $this->actAs('org_admin', $this->orgA);

        $driver = \App\Models\Driver::query()->whereKey($this->anaId)->firstOrFail();
        $data = app(\App\Http\Controllers\DriverPerformanceController::class)
            ->detail($driver, request(), app(AdminContext::class), 5);

        $this->assertCount(5, $data['trips']);
        $this->assertNotInstanceOf(\Illuminate\Contracts\Pagination\Paginator::class, $data['trips']);
        $this->assertArrayHasKey('notice', $data);
        $this->assertSame(12, $data['row']['trips'], 'The summary still counts every trip in the period.');
    }

    public function test_the_trips_pagination_uses_bootstrap_not_unsized_tailwind_icons(): void
    {
        // The default Laravel pagination view is written for Tailwind, which this console does not
        // load, so its arrow icons render at full size (a giant chevron over the page).
        for ($i = 0; $i < 30; $i++) {
            $this->seedTrip($this->orgA, $this->anaId, 'on_time', ['started_at' => now()->subDays(2)->addMinutes($i)->format('Y-m-d H:i:s')]);
        }
        $this->actAs('org_admin', $this->orgA);

        $this->get('/analytics/drivers/'.$this->anaId)->assertOk()
            ->assertSee('class="pagination"', false)
            ->assertDontSee('w-5 h-5', false);
    }

    public function test_a_super_admin_is_refused(): void
    {
        $this->actAs('super_admin', null);

        $this->get('/analytics/drivers')->assertForbidden();
    }
}
