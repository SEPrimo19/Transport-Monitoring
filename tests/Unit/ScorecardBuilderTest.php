<?php

namespace Tests\Unit;

use App\Support\ScorecardBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Builds the rows of the driver scorecard from plain arrays, so every rule about how a
 * person is shown can be pinned without a database. The ones that matter most are the
 * degenerate inputs: a driver with no trips, a driver with no name, a driver whose trips
 * have no timetable. Each must read as exactly what it is, never as a 0%, a NaN or a blank.
 */
class ScorecardBuilderTest extends TestCase
{
    private function driver(string $id, ?string $name = 'Ana Cruz', ?string $code = 'ana', bool $active = true): array
    {
        return ['profile_id' => $id, 'name' => $name, 'employee_code' => $code, 'is_active' => $active];
    }

    private function agg(array $overrides = []): array
    {
        return array_merge([
            'trips' => 10, 'minutes' => 600, 'distance_km' => 120.0,
            'on_time' => 6, 'early' => 1, 'late' => 1, 'no_timetable' => 2, 'unscheduled' => 0,
            'partial' => 0, 'avg_speed_kmh' => 41.5, 'max_speed_kmh' => 68.0, 'overspeed_share' => 0.05,
        ], $overrides);
    }

    private function report(string $driverId, string $category = 'driver_behavior', string $status = 'submitted', ?string $outcome = null, bool $anon = false): array
    {
        return ['driver_id' => $driverId, 'category' => $category, 'status' => $status, 'outcome' => $outcome, 'is_anonymous' => $anon];
    }

    private function row(array $drivers, array $aggs, array $reports, int $i = 0): array
    {
        return ScorecardBuilder::build($drivers, $aggs, $reports)[$i];
    }

    public function test_a_driver_with_no_trips_is_listed_without_dividing_by_zero(): void
    {
        $row = $this->row([$this->driver('d1')], [], []);

        $this->assertSame(0, $row['trips']);
        $this->assertNull($row['on_time_pct']);
        $this->assertNull($row['rate_per_100']);
        $this->assertNull($row['avg_speed_kmh']);
        $this->assertSame(0.0, $row['hours']);
        $this->assertSame(0, $row['complaints']['total']);
    }

    public function test_a_driver_with_no_profile_name_falls_back_to_the_employee_code(): void
    {
        $this->assertSame('ana', $this->row([$this->driver('d1', null, 'ana')], [], [])['name']);
        $this->assertSame('Unnamed driver', $this->row([$this->driver('d1', null, null)], [], [])['name'],
            'A removed or nameless account must never render as a blank row.');
        $this->assertSame('ana', $this->row([$this->driver('d1', '   ', 'ana')], [], [])['name'],
            'Whitespace is not a name.');
    }

    public function test_an_inactive_driver_is_hidden_only_when_there_is_nothing_to_show(): void
    {
        $drivers = [$this->driver('quiet', 'Quiet', 'q', false), $this->driver('worked', 'Worked', 'w', false)];
        $rows = ScorecardBuilder::build($drivers, ['worked' => $this->agg()], []);

        $this->assertCount(1, $rows);
        $this->assertSame('worked', $rows[0]['driver_id']);
    }

    public function test_only_driver_behaviour_counts_as_a_complaint(): void
    {
        $reports = [
            $this->report('d1', 'driver_behavior'),
            $this->report('d1', 'driver_behavior'),
            $this->report('d1', 'safety'),
            $this->report('d1', 'vehicle_condition'),
            $this->report('d1', 'route_issue'),
            $this->report('d1', 'other'),
        ];
        $row = $this->row([$this->driver('d1')], ['d1' => $this->agg()], $reports);

        $this->assertSame(2, $row['complaints']['total']);
        $this->assertSame(1, $row['safety_related'], 'Safety is shown, but kept apart from conduct.');
    }

    public function test_resolved_complaints_are_split_by_outcome_and_never_assumed_upheld(): void
    {
        $reports = [
            $this->report('d1', 'driver_behavior', 'resolved', 'upheld'),
            $this->report('d1', 'driver_behavior', 'resolved', 'not_upheld'),
            $this->report('d1', 'driver_behavior', 'resolved', null),
            $this->report('d1', 'driver_behavior', 'submitted'),
            $this->report('d1', 'driver_behavior', 'reviewing'),
            $this->report('d1', 'driver_behavior', 'dismissed'),
        ];
        $c = $this->row([$this->driver('d1')], ['d1' => $this->agg()], $reports)['complaints'];

        $this->assertSame(1, $c['upheld']);
        $this->assertSame(1, $c['not_upheld']);
        $this->assertSame(1, $c['outcome_not_recorded']);
        $this->assertSame(1, $c['received']);
        $this->assertSame(1, $c['under_review']);
        $this->assertSame(1, $c['dismissed']);
        $this->assertSame(6, $c['total']);
    }

    public function test_reports_for_another_driver_are_not_counted(): void
    {
        $row = $this->row([$this->driver('d1')], ['d1' => $this->agg()], [$this->report('someone-else')]);

        $this->assertSame(0, $row['complaints']['total']);
    }

    public function test_the_anonymous_share_is_shown_and_absent_when_there_are_no_complaints(): void
    {
        $none = $this->row([$this->driver('d1')], ['d1' => $this->agg()], []);
        $this->assertNull($none['anonymous_share']);

        $half = $this->row([$this->driver('d1')], ['d1' => $this->agg()],
            [$this->report('d1', anon: true), $this->report('d1', anon: false)]);
        $this->assertSame(0.5, $half['anonymous_share']);
    }

    public function test_a_rate_appears_only_from_thirty_trips(): void
    {
        $reports = [$this->report('d1'), $this->report('d1'), $this->report('d1')];

        $this->assertNull($this->row([$this->driver('d1')], ['d1' => $this->agg(['trips' => 29])], $reports)['rate_per_100']);
        $this->assertSame(10.0, $this->row([$this->driver('d1')], ['d1' => $this->agg(['trips' => 30])], $reports)['rate_per_100']);
    }

    public function test_a_driver_whose_trips_have_no_timetable_is_not_shown_as_never_on_time(): void
    {
        $agg = $this->agg(['trips' => 8, 'on_time' => 0, 'early' => 0, 'late' => 0, 'no_timetable' => 8]);
        $row = $this->row([$this->driver('d1')], ['d1' => $agg], []);

        $this->assertSame(0, $row['scored']);
        $this->assertNull($row['on_time_pct'], '0% would say they were late every time. They were never scored.');
        $this->assertSame(8, $row['no_timetable']);
    }

    public function test_punctuality_is_computed_over_scored_trips_only(): void
    {
        $row = $this->row([$this->driver('d1')], ['d1' => $this->agg()], []);

        $this->assertSame(8, $row['scored']);
        $this->assertSame(75.0, $row['on_time_pct']);
    }

    public function test_hours_and_distance_are_rounded_for_display(): void
    {
        $row = $this->row([$this->driver('d1')], ['d1' => $this->agg(['minutes' => 650, 'distance_km' => 120.449])], []);

        $this->assertSame(10.8, $row['hours']);
        $this->assertSame(120.4, $row['distance_km']);
    }

    public function test_rows_are_ordered_by_name_never_by_complaints(): void
    {
        $drivers = [$this->driver('3', 'zed'), $this->driver('1', 'Ana'), $this->driver('2', 'bob')];
        $aggs = ['1' => $this->agg(), '2' => $this->agg(), '3' => $this->agg()];
        $reports = [$this->report('3'), $this->report('3'), $this->report('3')];

        $names = array_column(ScorecardBuilder::build($drivers, $aggs, $reports), 'name');

        $this->assertSame(['Ana', 'bob', 'zed'], $names, 'A scorecard that sorts by complaints is a leaderboard.');
    }
}
