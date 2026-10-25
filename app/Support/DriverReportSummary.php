<?php

namespace App\Support;

/**
 * The headline numbers and the per-route table of the printed driver performance report.
 *
 * Pure: it takes the rows ScorecardBuilder already produced plus one aggregate per
 * driver-and-route, and returns arrays. A figure nobody measured is null, never 0, and a route
 * that no longer exists reads as a dash rather than breaking the page. Rows are ordered by
 * name, never by how well a driver did: a printed report must not become a league table.
 */
final class DriverReportSummary
{
    /**
     * @param  array<int, array<string, mixed>>  $rows  ScorecardBuilder::build() output
     * @return array{drivers_with_trips: int, trips: int, on_time_pct: ?float, complaints: int}
     */
    public static function figures(array $rows): array
    {
        $trips = 0;
        $withTrips = 0;
        $onTime = 0;
        $scored = 0;
        $complaints = 0;

        foreach ($rows as $row) {
            $t = (int) $row['trips'];
            $trips += $t;
            $withTrips += $t > 0 ? 1 : 0;
            $onTime += (int) $row['on_time'];
            $scored += (int) $row['scored'];
            $complaints += (int) $row['complaints']['total'];
        }

        return [
            'drivers_with_trips' => $withTrips,
            'trips' => $trips,
            'on_time_pct' => ScorecardPolicy::onTimePercent($onTime, $scored),
            'complaints' => $complaints,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows  ScorecardBuilder::build() output
     * @param  array<int, array{driver_id: string, route_id: ?string, trips: int, on_time: int, scored: int}>  $aggregates
     * @param  array<string, string>  $routeCodes  route id => code
     * @return array<int, array{driver_id: string, name: string, employee_code: ?string, route_code: string, trips: int, on_time_pct: ?float}>
     */
    public static function perRoute(array $rows, array $aggregates, array $routeCodes): array
    {
        $drivers = [];
        foreach ($rows as $row) {
            $drivers[$row['driver_id']] = $row;
        }

        $out = [];
        foreach ($aggregates as $a) {
            $driver = $drivers[$a['driver_id']] ?? null;
            if ($driver === null) {
                continue;
            }

            $out[] = [
                'driver_id' => (string) $a['driver_id'],
                'name' => (string) $driver['name'],
                'employee_code' => $driver['employee_code'],
                'route_code' => $routeCodes[$a['route_id'] ?? ''] ?? '—',
                'trips' => (int) $a['trips'],
                'on_time_pct' => ScorecardPolicy::onTimePercent((int) $a['on_time'], (int) $a['scored']),
            ];
        }

        usort($out, static fn (array $x, array $y): int => [mb_strtolower($x['name']), (string) $x['employee_code'], $x['route_code']]
            <=> [mb_strtolower($y['name']), (string) $y['employee_code'], $y['route_code']]);

        return $out;
    }
}
