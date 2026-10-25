<?php

namespace Tests\Unit;

use App\Support\FleetReportBuilder;
use PHPUnit\Framework\TestCase;

/**
 * The tables of the printed vehicles and routes report. Pure: arrays in, arrays out. The cases
 * that matter are the ones a printed page must not get wrong: a vehicle or route that is no
 * longer on file, a figure nobody measured (null, never 0), and the line between an idle
 * vehicle and one that is simply out of service.
 */
class FleetReportBuilderTest extends TestCase
{
    private function vagg(?string $id, array $over = []): array
    {
        return $over + [
            'vehicle_id' => $id, 'trips' => 10, 'minutes' => 600, 'distance_km' => 100.0, 'distance_trips' => 10,
            'on_time' => 8, 'early' => 1, 'late' => 1,
        ];
    }

    private function ragg(?string $id, array $over = []): array
    {
        return $over + [
            'route_id' => $id, 'trips' => 10, 'minutes' => 700, 'on_time' => 8, 'early' => 1, 'late' => 1,
            'no_timetable' => 0, 'unscheduled' => 0,
        ];
    }

    private function vehicle(?string $fleet, ?string $plate, string $status = 'active'): array
    {
        return ['fleet_code' => $fleet, 'plate' => $plate, 'status' => $status];
    }

    public function test_vehicle_rows_use_fleet_code_then_plate_and_sort_by_label(): void
    {
        $rows = FleetReportBuilder::vehicles(
            [$this->vagg('v3'), $this->vagg('v2'), $this->vagg('v1')],
            ['v1' => $this->vehicle('B-02', 'ABC 123'), 'v2' => $this->vehicle(null, 'ZZZ 999'), 'v3' => $this->vehicle('A-01', 'DEF 456')]
        );

        $this->assertSame(['A-01', 'B-02', 'ZZZ 999'], array_column($rows, 'label'));
        $this->assertSame('', $rows[2]['plate'], 'A plate already used as the label is not repeated underneath.');
        $this->assertSame('DEF 456', $rows[0]['plate']);
    }

    public function test_two_vehicles_with_the_same_fleet_code_stay_two_rows_told_apart_by_plate(): void
    {
        $rows = FleetReportBuilder::vehicles(
            [$this->vagg('a'), $this->vagg('b')],
            ['a' => $this->vehicle('BUS-1', 'XYZ 222'), 'b' => $this->vehicle('BUS-1', 'XYZ 111')]
        );

        $this->assertSame(['XYZ 111', 'XYZ 222'], array_column($rows, 'plate'));
    }

    public function test_a_vehicle_no_longer_on_file_merges_into_one_last_row(): void
    {
        $rows = FleetReportBuilder::vehicles(
            [$this->vagg(null, ['trips' => 2]), $this->vagg('gone', ['trips' => 3]), $this->vagg('v1', ['trips' => 5])],
            ['v1' => $this->vehicle('A-01', 'AAA 111')]
        );

        $this->assertSame(['A-01', 'Vehicle no longer on file'], array_column($rows, 'label'));
        $this->assertSame(5, $rows[1]['trips']);
    }

    public function test_vehicle_distance_is_null_when_never_measured_and_a_sum_otherwise(): void
    {
        $rows = FleetReportBuilder::vehicles(
            [$this->vagg('a', ['distance_km' => null, 'distance_trips' => 0]), $this->vagg('b', ['distance_km' => 12.5, 'distance_trips' => 4])],
            ['a' => $this->vehicle('A', null), 'b' => $this->vehicle('B', null)]
        );

        $this->assertNull($rows[0]['distance_km']);
        $this->assertSame(12.5, $rows[1]['distance_km']);
        $this->assertSame(4, $rows[1]['distance_trips']);
    }

    public function test_vehicle_on_time_is_null_not_zero_when_nothing_was_scored(): void
    {
        $rows = FleetReportBuilder::vehicles(
            [$this->vagg('a', ['on_time' => 0, 'early' => 0, 'late' => 0])],
            ['a' => $this->vehicle('A', null)]
        );

        $this->assertNull($rows[0]['on_time_pct']);
    }

    public function test_hours_come_from_minutes(): void
    {
        $rows = FleetReportBuilder::vehicles([$this->vagg('a', ['minutes' => 90])], ['a' => $this->vehicle('A', null)]);

        $this->assertSame(1.5, $rows[0]['hours']);
    }

    public function test_idle_is_active_vehicles_with_no_trips_only(): void
    {
        $idle = FleetReportBuilder::idle([
            'ran' => $this->vehicle('A-01', 'AAA 111'),
            'still' => $this->vehicle('B-02', 'BBB 222'),
            'shop' => $this->vehicle('C-03', 'CCC 333', 'maintenance'),
            'retired' => $this->vehicle('D-04', 'DDD 444', 'inactive'),
        ], ['ran']);

        $this->assertSame(['B-02'], array_column($idle, 'label'));
    }

    public function test_idle_is_empty_when_every_active_vehicle_ran(): void
    {
        $this->assertSame([], FleetReportBuilder::idle(['a' => $this->vehicle('A', null)], ['a']));
    }

    public function test_route_rows_compare_average_with_stored_duration(): void
    {
        $rows = FleetReportBuilder::routes(
            [$this->ragg('r1', ['trips' => 10, 'minutes' => 700])],
            ['r1' => ['code' => 'ALN-CAL', 'name' => 'Allen to Calbayog', 'duration_minutes' => 60]]
        );

        $this->assertSame(70, $rows[0]['avg_duration_min']);
        $this->assertSame(60, $rows[0]['stored_duration_min']);
        $this->assertSame(10, $rows[0]['difference_min']);
    }

    public function test_a_route_with_no_stored_duration_has_null_stored_and_difference(): void
    {
        $rows = FleetReportBuilder::routes([$this->ragg('r1')], ['r1' => ['code' => 'A', 'name' => 'a', 'duration_minutes' => null]]);

        $this->assertNull($rows[0]['stored_duration_min']);
        $this->assertNull($rows[0]['difference_min']);
        $this->assertSame(70, $rows[0]['avg_duration_min']);
    }

    public function test_a_route_with_no_trips_has_a_null_average_and_difference(): void
    {
        $rows = FleetReportBuilder::routes(
            [$this->ragg('r1', ['trips' => 0, 'minutes' => 0, 'on_time' => 0, 'early' => 0, 'late' => 0])],
            ['r1' => ['code' => 'A', 'name' => 'a', 'duration_minutes' => 60]]
        );

        $this->assertNull($rows[0]['avg_duration_min']);
        $this->assertNull($rows[0]['difference_min']);
        $this->assertNull($rows[0]['on_time_pct']);
    }

    public function test_unresolved_routes_merge_into_one_last_row(): void
    {
        $rows = FleetReportBuilder::routes(
            [$this->ragg(null, ['trips' => 1]), $this->ragg('gone', ['trips' => 2]), $this->ragg('r1', ['trips' => 4])],
            ['r1' => ['code' => 'A', 'name' => 'a', 'duration_minutes' => 60]]
        );

        $this->assertSame(['A', 'Not linked to a route'], array_column($rows, 'route_code'));
        $this->assertSame(3, $rows[1]['trips']);
        $this->assertNull($rows[1]['stored_duration_min']);
        $this->assertNull($rows[1]['difference_min']);
    }

    public function test_route_rows_carry_unscored_counts_so_a_missing_timetable_shows(): void
    {
        $rows = FleetReportBuilder::routes(
            [$this->ragg('r1', ['on_time' => 0, 'early' => 0, 'late' => 0, 'no_timetable' => 7, 'unscheduled' => 3])],
            ['r1' => ['code' => 'A', 'name' => 'a', 'duration_minutes' => 60]]
        );

        $this->assertNull($rows[0]['on_time_pct']);
        $this->assertSame(0, $rows[0]['scored']);
        $this->assertSame(7, $rows[0]['no_timetable']);
        $this->assertSame(3, $rows[0]['unscheduled']);
    }

    public function test_rows_are_sorted_by_label_not_by_performance(): void
    {
        $vehicles = FleetReportBuilder::vehicles(
            [$this->vagg('z', ['on_time' => 10, 'early' => 0, 'late' => 0]), $this->vagg('a', ['on_time' => 0, 'early' => 0, 'late' => 10])],
            ['z' => $this->vehicle('Z', null), 'a' => $this->vehicle('A', null)]
        );
        $routes = FleetReportBuilder::routes(
            [$this->ragg('z', ['trips' => 99]), $this->ragg('a', ['trips' => 1])],
            ['z' => ['code' => 'ZZ', 'name' => '', 'duration_minutes' => null], 'a' => ['code' => 'AA', 'name' => '', 'duration_minutes' => null]]
        );

        $this->assertSame(['A', 'Z'], array_column($vehicles, 'label'));
        $this->assertSame(['AA', 'ZZ'], array_column($routes, 'route_code'));
    }

    public function test_figures_for_nothing_are_zero(): void
    {
        $this->assertSame(
            ['trips' => 0, 'vehicles_with_trips' => 0, 'idle_vehicles' => 0, 'routes_run' => 0],
            FleetReportBuilder::figures([], [], [])
        );
    }

    public function test_figures_count_vehicles_idle_and_routes(): void
    {
        $vehicles = FleetReportBuilder::vehicles(
            [$this->vagg('a', ['trips' => 3]), $this->vagg('b', ['trips' => 0, 'minutes' => 0])],
            ['a' => $this->vehicle('A', null), 'b' => $this->vehicle('B', null)]
        );
        $routes = FleetReportBuilder::routes(
            [$this->ragg('r1', ['trips' => 3]), $this->ragg(null, ['trips' => 2])],
            ['r1' => ['code' => 'A', 'name' => 'a', 'duration_minutes' => null]]
        );

        $f = FleetReportBuilder::figures($vehicles, [['label' => 'C', 'plate' => '']], $routes);

        $this->assertSame(3, $f['trips']);
        $this->assertSame(1, $f['vehicles_with_trips']);
        $this->assertSame(1, $f['idle_vehicles']);
        $this->assertSame(1, $f['routes_run'], 'The not-linked row is not a route.');
    }
}
