<?php

namespace Tests\Unit;

use App\Support\DriverReportSummary;
use PHPUnit\Framework\TestCase;

/**
 * The headline numbers and the per-route table of the printed driver report. Pure: arrays in,
 * arrays out. The cases that matter are the ones a printed page must not get wrong, a figure
 * that was never measured (null, not 0), a route that no longer exists, and two people who
 * happen to share a name.
 */
class DriverReportSummaryTest extends TestCase
{
    private function row(string $id, string $name, string $code, int $trips, int $onTime, int $scored, int $complaints = 0): array
    {
        return [
            'driver_id' => $id, 'name' => $name, 'employee_code' => $code,
            'trips' => $trips, 'on_time' => $onTime, 'scored' => $scored,
            'complaints' => ['total' => $complaints],
        ];
    }

    public function test_figures_for_an_empty_period_are_zero_and_on_time_is_null(): void
    {
        $f = DriverReportSummary::figures([]);

        $this->assertSame(0, $f['drivers_with_trips']);
        $this->assertSame(0, $f['trips']);
        $this->assertSame(0, $f['complaints']);
        $this->assertNull($f['on_time_pct']);
    }

    public function test_on_time_is_null_not_zero_when_no_trip_was_scored(): void
    {
        // Trips happened but none had a timetable slot to be judged against.
        $f = DriverReportSummary::figures([$this->row('d1', 'Ana', 'A1', 5, 0, 0)]);

        $this->assertSame(5, $f['trips']);
        $this->assertNull($f['on_time_pct']);
    }

    public function test_figures_sum_across_drivers(): void
    {
        $f = DriverReportSummary::figures([
            $this->row('d1', 'Ana', 'A1', 10, 8, 10, 1),
            $this->row('d2', 'Ben', 'B1', 10, 4, 10, 2),
            $this->row('d3', 'Cy', 'C1', 0, 0, 0),
        ]);

        $this->assertSame(2, $f['drivers_with_trips']);
        $this->assertSame(20, $f['trips']);
        $this->assertSame(60.0, $f['on_time_pct']);
        $this->assertSame(3, $f['complaints']);
    }

    public function test_per_route_rows_use_a_dash_for_a_deleted_route(): void
    {
        $rows = DriverReportSummary::perRoute(
            [$this->row('d1', 'Ana', 'A1', 3, 3, 3)],
            [['driver_id' => 'd1', 'route_id' => 'gone', 'trips' => 3, 'on_time' => 3, 'scored' => 3]],
            []
        );

        $this->assertSame('—', $rows[0]['route_code']);
        $this->assertSame(100.0, $rows[0]['on_time_pct']);
    }

    public function test_a_route_with_no_scored_trip_has_a_null_on_time(): void
    {
        $rows = DriverReportSummary::perRoute(
            [$this->row('d1', 'Ana', 'A1', 2, 0, 0)],
            [['driver_id' => 'd1', 'route_id' => 'r1', 'trips' => 2, 'on_time' => 0, 'scored' => 0]],
            ['r1' => 'ALN-CAL']
        );

        $this->assertNull($rows[0]['on_time_pct']);
    }

    public function test_per_route_rows_are_sorted_by_name_not_by_performance(): void
    {
        $rows = DriverReportSummary::perRoute(
            [$this->row('d1', 'Zed', 'Z1', 4, 4, 4), $this->row('d2', 'Abe', 'A1', 4, 0, 4)],
            [
                ['driver_id' => 'd1', 'route_id' => 'r1', 'trips' => 4, 'on_time' => 4, 'scored' => 4],
                ['driver_id' => 'd2', 'route_id' => 'r1', 'trips' => 4, 'on_time' => 0, 'scored' => 4],
            ],
            ['r1' => 'ALN-CAL']
        );

        $this->assertSame(['Abe', 'Zed'], array_column($rows, 'name'));
    }

    public function test_two_drivers_with_the_same_name_stay_two_groups(): void
    {
        $rows = DriverReportSummary::perRoute(
            [$this->row('d1', 'Bea Francisco', '0530', 2, 2, 2), $this->row('d2', 'Bea Francisco', 'bea', 2, 1, 2)],
            [
                ['driver_id' => 'd2', 'route_id' => 'r1', 'trips' => 2, 'on_time' => 1, 'scored' => 2],
                ['driver_id' => 'd1', 'route_id' => 'r1', 'trips' => 2, 'on_time' => 2, 'scored' => 2],
                ['driver_id' => 'd1', 'route_id' => 'r2', 'trips' => 1, 'on_time' => 1, 'scored' => 1],
            ],
            ['r1' => 'ALN-CAL', 'r2' => 'ALN-CAT']
        );

        $this->assertCount(3, $rows);
        $this->assertSame(['0530', '0530', 'bea'], array_column($rows, 'employee_code'));
        $this->assertSame(['ALN-CAL', 'ALN-CAT', 'ALN-CAL'], array_column($rows, 'route_code'));
    }

    public function test_aggregates_for_a_driver_not_in_the_scorecard_are_left_out(): void
    {
        $rows = DriverReportSummary::perRoute(
            [$this->row('d1', 'Ana', 'A1', 1, 1, 1)],
            [['driver_id' => 'stranger', 'route_id' => 'r1', 'trips' => 9, 'on_time' => 9, 'scored' => 9]],
            ['r1' => 'ALN-CAL']
        );

        $this->assertSame([], $rows);
    }
}
