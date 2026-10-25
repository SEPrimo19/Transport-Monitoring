<?php

namespace Tests\Unit;

use App\Support\TripReportBuilder;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

/**
 * The figures and tables of the printed trips report. Pure: arrays in, arrays out. The cases
 * that matter are the ones a printed page must not get wrong: a figure nobody measured (null,
 * never 0), a route that no longer exists, and a trip that falls either side of midnight.
 */
class TripReportBuilderTest extends TestCase
{
    private function agg(string $route, array $over = []): array
    {
        return $over + [
            'route_id' => $route, 'trips' => 10, 'minutes' => 600, 'distance_km' => 100.0,
            'distance_trips' => 10, 'on_time' => 8, 'early' => 1, 'late' => 1, 'no_timetable' => 0, 'unscheduled' => 0,
        ];
    }

    public function test_figures_for_nothing_are_zero_with_null_on_time_and_distance(): void
    {
        $f = TripReportBuilder::figures([]);

        $this->assertSame(0, $f['trips']);
        $this->assertSame(0.0, $f['hours']);
        $this->assertNull($f['distance_km']);
        $this->assertSame(0, $f['distance_trips']);
        $this->assertNull($f['on_time_pct']);
    }

    public function test_distance_is_null_when_no_trip_had_one_and_a_sum_otherwise(): void
    {
        $none = TripReportBuilder::figures([$this->agg('r1', ['distance_km' => null, 'distance_trips' => 0])]);
        $this->assertNull($none['distance_km']);

        $some = TripReportBuilder::figures([
            $this->agg('r1', ['distance_km' => 12.5, 'distance_trips' => 1]),
            $this->agg('r2', ['distance_km' => null, 'distance_trips' => 0]),
        ]);
        $this->assertSame(12.5, $some['distance_km']);
        $this->assertSame(1, $some['distance_trips']);
        $this->assertSame(20, $some['trips']);
    }

    public function test_on_time_is_null_not_zero_when_nothing_was_scored(): void
    {
        $f = TripReportBuilder::figures([
            $this->agg('r1', ['on_time' => 0, 'early' => 0, 'late' => 0, 'no_timetable' => 10]),
        ]);

        $this->assertNull($f['on_time_pct']);
    }

    public function test_hours_come_from_minutes(): void
    {
        $f = TripReportBuilder::figures([$this->agg('r1', ['minutes' => 90])]);

        $this->assertSame(1.5, $f['hours']);
    }

    public function test_by_route_uses_a_dash_for_an_unknown_route_and_sorts_by_code(): void
    {
        $rows = TripReportBuilder::byRoute(
            [$this->agg('zz'), $this->agg('gone'), $this->agg('aa')],
            ['aa' => ['code' => 'ALN-CAL', 'name' => 'Allen to Calbayog'], 'zz' => ['code' => 'CAL-ALN', 'name' => 'Calbayog to Allen']]
        );

        $this->assertSame(['ALN-CAL', 'CAL-ALN', '—'], array_column($rows, 'route_code'));
        $this->assertSame('', $rows[2]['route_name']);
    }

    public function test_by_route_average_duration_is_rounded_and_null_with_no_trips(): void
    {
        $rows = TripReportBuilder::byRoute(
            [$this->agg('r1', ['trips' => 3, 'minutes' => 100]), $this->agg('r2', ['trips' => 0, 'minutes' => 0])],
            ['r1' => ['code' => 'A', 'name' => 'a'], 'r2' => ['code' => 'B', 'name' => 'b']]
        );

        $this->assertSame(33, $rows[0]['avg_duration_min']);
        $this->assertNull($rows[1]['avg_duration_min']);
    }

    public function test_by_route_carries_on_time_percent_and_distance_per_row(): void
    {
        $rows = TripReportBuilder::byRoute(
            [$this->agg('r1', ['on_time' => 3, 'early' => 0, 'late' => 1, 'distance_km' => null, 'distance_trips' => 0])],
            ['r1' => ['code' => 'A', 'name' => 'a']]
        );

        $this->assertSame(75.0, $rows[0]['on_time_pct']);
        $this->assertNull($rows[0]['distance_km']);
    }

    public function test_granularity_is_day_up_to_31_days_and_week_after(): void
    {
        $from = CarbonImmutable::parse('2026-10-01')->startOfDay();

        $this->assertSame('day', TripReportBuilder::granularity($from, CarbonImmutable::parse('2026-10-31')->endOfDay()));
        $this->assertSame('week', TripReportBuilder::granularity($from, CarbonImmutable::parse('2026-11-01')->endOfDay()));
    }

    public function test_by_period_groups_by_day_and_omits_days_without_trips(): void
    {
        $rows = TripReportBuilder::byPeriod([
            ['started_at' => CarbonImmutable::parse('2026-10-05 08:00'), 'punctuality' => 'on_time'],
            ['started_at' => CarbonImmutable::parse('2026-10-05 15:00'), 'punctuality' => 'late'],
            ['started_at' => CarbonImmutable::parse('2026-10-09 06:00'), 'punctuality' => 'on_time'],
        ], 'day');

        $this->assertSame(['2026-10-05', '2026-10-09'], array_column($rows, 'key'));
        $this->assertSame([2, 1], array_column($rows, 'trips'));
        $this->assertSame(50.0, $rows[0]['on_time_pct']);
        $this->assertSame('Oct 5', $rows[0]['label']);
    }

    public function test_a_trip_just_before_midnight_lands_on_its_own_day(): void
    {
        $rows = TripReportBuilder::byPeriod([
            ['started_at' => CarbonImmutable::parse('2026-10-05 23:59:59'), 'punctuality' => 'on_time'],
            ['started_at' => CarbonImmutable::parse('2026-10-06 00:00:00'), 'punctuality' => 'on_time'],
        ], 'day');

        $this->assertSame(['2026-10-05', '2026-10-06'], array_column($rows, 'key'));
    }

    public function test_by_period_groups_by_week_starting_monday(): void
    {
        $rows = TripReportBuilder::byPeriod([
            ['started_at' => CarbonImmutable::parse('2026-10-04 12:00'), 'punctuality' => 'on_time'],   // a Sunday
            ['started_at' => CarbonImmutable::parse('2026-10-05 12:00'), 'punctuality' => 'on_time'],   // the Monday after
            ['started_at' => CarbonImmutable::parse('2026-10-11 12:00'), 'punctuality' => 'late'],      // that week's Sunday
        ], 'week');

        $this->assertSame(['2026-09-28', '2026-10-05'], array_column($rows, 'key'));
        $this->assertSame([1, 2], array_column($rows, 'trips'));
        $this->assertSame('Week of Oct 5', $rows[1]['label']);
    }

    public function test_a_period_with_no_scored_trip_has_null_on_time(): void
    {
        $rows = TripReportBuilder::byPeriod([
            ['started_at' => CarbonImmutable::parse('2026-10-05 08:00'), 'punctuality' => 'no_timetable'],
            ['started_at' => CarbonImmutable::parse('2026-10-05 09:00'), 'punctuality' => 'unscheduled'],
        ], 'day');

        $this->assertNull($rows[0]['on_time_pct']);
    }

    public function test_by_period_with_no_trips_is_empty(): void
    {
        $this->assertSame([], TripReportBuilder::byPeriod([], 'day'));
    }
}
