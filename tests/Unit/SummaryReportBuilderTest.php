<?php

namespace Tests\Unit;

use App\Support\SummaryReportBuilder;
use PHPUnit\Framework\TestCase;

/**
 * The measures behind the dashboard summary. Pure: arrays in, arrays out. One list of measures
 * feeds both the key-figures strip and the CSV, so the two cannot disagree. The cases that matter
 * are the ones a board pack must not get wrong: an unmeasured figure staying null (never 0), an
 * idle count under a route filter being omitted, and no individual appearing in any value.
 */
class SummaryReportBuilderTest extends TestCase
{
    private function trips(array $over = []): array
    {
        return ['figures' => $over + ['trips' => 40, 'hours' => 30.5, 'distance_km' => 250.0, 'distance_trips' => 40, 'on_time_pct' => 90.0]];
    }

    private function complaints(array $over = [], ?array $categories = null): array
    {
        return [
            'figures' => $over + ['received' => 6, 'closed' => 4, 'open' => 2, 'median_close_minutes' => 120, 'slowest_close_minutes' => 600],
            'byCategory' => $categories ?? [
                ['category' => 'driver_behavior', 'scope' => 'driver', 'received' => 3],
                ['category' => 'safety', 'scope' => 'safety', 'received' => 1],
                ['category' => 'other', 'scope' => 'not_driver', 'received' => 2],
            ],
        ];
    }

    private function fleet(array $over = [], bool $filtered = false): array
    {
        return ['figures' => $over + ['trips' => 40, 'vehicles_with_trips' => 5, 'idle_vehicles' => 2, 'routes_run' => 3], 'routeFiltered' => $filtered];
    }

    private function drivers(array $over = []): array
    {
        return $over + ['drivers_with_trips' => 6, 'trips' => 40, 'on_time_pct' => 88.5, 'complaints' => 4];
    }

    private function value(array $measures, string $area, string $measure): mixed
    {
        foreach ($measures as $m) {
            if ($m['area'] === $area && $m['measure'] === $measure) {
                return $m['value'];
            }
        }

        $this->fail("No measure $area / $measure");
    }

    public function test_measures_come_in_a_fixed_order_with_their_areas(): void
    {
        $m = SummaryReportBuilder::measures($this->trips(), $this->complaints(), $this->fleet(), $this->drivers());

        $this->assertSame([
            ['Trips', 'Trips'], ['Trips', 'Hours in service'], ['Trips', 'Distance (km)'], ['Trips', 'On time %'],
            ['Complaints', 'Received'], ['Complaints', 'Closed'], ['Complaints', 'Still open'],
            ['Complaints', 'Median time to close (minutes)'], ['Complaints', 'Slowest time to close (minutes)'],
            ['Complaints', 'Driver-conduct complaints'],
            ['Fleet', 'Vehicles with trips'], ['Fleet', 'Idle vehicles'], ['Fleet', 'Routes run'],
            ['Drivers', 'Drivers with trips'], ['Drivers', 'On time % (all drivers)'],
        ], array_map(static fn (array $x): array => [$x['area'], $x['measure']], $m));
        $this->assertSame(250.0, $this->value($m, 'Trips', 'Distance (km)'));
    }

    public function test_null_values_stay_null_not_zero(): void
    {
        $m = SummaryReportBuilder::measures(
            $this->trips(['distance_km' => null, 'distance_trips' => 0, 'on_time_pct' => null]),
            $this->complaints(['median_close_minutes' => null, 'slowest_close_minutes' => null]),
            $this->fleet([], true),
            $this->drivers(['on_time_pct' => null])
        );

        $this->assertNull($this->value($m, 'Trips', 'Distance (km)'));
        $this->assertNull($this->value($m, 'Trips', 'On time %'));
        $this->assertNull($this->value($m, 'Complaints', 'Median time to close (minutes)'));
        $this->assertNull($this->value($m, 'Complaints', 'Slowest time to close (minutes)'));
        $this->assertNull($this->value($m, 'Fleet', 'Idle vehicles'), 'Idle is omitted under a route filter.');
        $this->assertNull($this->value($m, 'Drivers', 'On time % (all drivers)'));
    }

    public function test_driver_conduct_complaints_come_from_the_driver_scope_row_only(): void
    {
        $m = SummaryReportBuilder::measures($this->trips(), $this->complaints(), $this->fleet(), $this->drivers());

        $this->assertSame(3, $this->value($m, 'Complaints', 'Driver-conduct complaints'));
    }

    public function test_driver_conduct_is_zero_when_no_category_row_has_the_driver_scope(): void
    {
        $m = SummaryReportBuilder::measures($this->trips(), $this->complaints([], []), $this->fleet(), $this->drivers());

        $this->assertSame(0, $this->value($m, 'Complaints', 'Driver-conduct complaints'));
    }

    public function test_headline_has_six_figures_in_order_and_keeps_nulls(): void
    {
        $m = SummaryReportBuilder::measures(
            $this->trips(['on_time_pct' => null]),
            $this->complaints(),
            $this->fleet([], true),
            $this->drivers()
        );

        $h = SummaryReportBuilder::headline($m);

        $this->assertSame(
            ['Trips', 'On time (of scored trips)', 'Hours in service', 'Complaints received', 'Complaints still open', 'Idle vehicles'],
            array_column($h, 'label')
        );
        $this->assertSame(40, $h[0]['value']);
        $this->assertNull($h[1]['value']);
        $this->assertSame(30.5, $h[2]['value']);
        $this->assertSame(6, $h[3]['value']);
        $this->assertSame(2, $h[4]['value']);
        $this->assertNull($h[5]['value']);
    }

    public function test_an_empty_period_gives_zero_counts_and_null_rates(): void
    {
        $m = SummaryReportBuilder::measures(
            $this->trips(['trips' => 0, 'hours' => 0.0, 'distance_km' => null, 'distance_trips' => 0, 'on_time_pct' => null]),
            $this->complaints(['received' => 0, 'closed' => 0, 'open' => 0, 'median_close_minutes' => null, 'slowest_close_minutes' => null], []),
            $this->fleet(['trips' => 0, 'vehicles_with_trips' => 0, 'idle_vehicles' => 0, 'routes_run' => 0]),
            $this->drivers(['drivers_with_trips' => 0, 'trips' => 0, 'on_time_pct' => null, 'complaints' => 0])
        );

        $this->assertSame(0, $this->value($m, 'Trips', 'Trips'));
        $this->assertSame(0, $this->value($m, 'Complaints', 'Received'));
        $this->assertSame(0, $this->value($m, 'Fleet', 'Idle vehicles'));
        $this->assertNull($this->value($m, 'Trips', 'On time %'));
    }

    public function test_drivers_area_has_no_per_driver_values(): void
    {
        $m = SummaryReportBuilder::measures($this->trips(), $this->complaints(), $this->fleet(), $this->drivers());

        foreach ($m as $x) {
            $this->assertTrue($x['value'] === null || is_int($x['value']) || is_float($x['value']), $x['measure'].' must be a plain number or null.');
        }
        $this->assertSame(
            ['Drivers with trips', 'On time % (all drivers)'],
            array_values(array_map(static fn (array $x): string => $x['measure'], array_filter($m, static fn (array $x): bool => $x['area'] === 'Drivers')))
        );
    }
}
