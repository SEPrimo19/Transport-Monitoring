<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The figures and tables of the printed trips report.
 *
 * Pure: arrays in, arrays out, no database. A figure nobody measured is null, never 0, and a
 * route that no longer exists reads as a dash rather than breaking the page. Routes are
 * ordered by code and periods by date; nothing here ranks anything by how well it did.
 *
 * On time is on_time / (on_time + early + late). A trip with no timetable, or one that left
 * too far from any scheduled departure, is never counted late: it is simply not scored.
 */
final class TripReportBuilder
{
    private const DASH = '—';

    /**
     * @param  array<int, array<string, mixed>>  $routeAggregates
     * @return array{trips: int, hours: float, distance_km: ?float, distance_trips: int, on_time_pct: ?float}
     */
    public static function figures(array $routeAggregates): array
    {
        $trips = 0;
        $minutes = 0;
        $distance = null;
        $distanceTrips = 0;
        $onTime = 0;
        $scored = 0;

        foreach ($routeAggregates as $a) {
            $trips += (int) $a['trips'];
            $minutes += (int) $a['minutes'];
            $distanceTrips += (int) $a['distance_trips'];
            if ($a['distance_km'] !== null) {
                $distance = ($distance ?? 0.0) + (float) $a['distance_km'];
            }
            $onTime += (int) $a['on_time'];
            $scored += (int) $a['on_time'] + (int) $a['early'] + (int) $a['late'];
        }

        return [
            'trips' => $trips,
            'hours' => round($minutes / 60, 1),
            'distance_km' => $distance === null ? null : round($distance, 1),
            'distance_trips' => $distanceTrips,
            'on_time_pct' => ScorecardPolicy::onTimePercent($onTime, $scored),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $routeAggregates
     * @param  array<string, array{code: string, name: string}>  $routes  route id => code and name
     * @return array<int, array<string, mixed>>
     */
    public static function byRoute(array $routeAggregates, array $routes): array
    {
        $rows = [];
        foreach ($routeAggregates as $a) {
            $route = $routes[(string) ($a['route_id'] ?? '')] ?? null;
            $trips = (int) $a['trips'];
            $scored = (int) $a['on_time'] + (int) $a['early'] + (int) $a['late'];

            $rows[] = [
                'route_code' => $route['code'] ?? self::DASH,
                'route_name' => $route['name'] ?? '',
                'trips' => $trips,
                'avg_duration_min' => $trips === 0 ? null : (int) round(((int) $a['minutes']) / $trips),
                'on_time' => (int) $a['on_time'],
                'early' => (int) $a['early'],
                'late' => (int) $a['late'],
                'no_timetable' => (int) $a['no_timetable'],
                'unscheduled' => (int) $a['unscheduled'],
                'on_time_pct' => ScorecardPolicy::onTimePercent((int) $a['on_time'], $scored),
                'distance_km' => $a['distance_km'] === null ? null : round((float) $a['distance_km'], 1),
                'distance_trips' => (int) $a['distance_trips'],
            ];
        }

        usort($rows, static fn (array $x, array $y): int => [mb_strtolower($x['route_code']), $x['route_name']]
            <=> [mb_strtolower($y['route_code']), $y['route_name']]);

        return $rows;
    }

    /** 'day' for a period of 31 days or fewer (inclusive of both ends), otherwise 'week'. */
    public static function granularity(CarbonInterface $from, CarbonInterface $to): string
    {
        $days = CarbonImmutable::parse($from->format('Y-m-d'))->diffInDays(CarbonImmutable::parse($to->format('Y-m-d'))) + 1;

        return $days <= 31 ? 'day' : 'week';
    }

    /**
     * @param  array<int, array{started_at: CarbonInterface, punctuality: string}>  $trips
     * @return array<int, array{key: string, label: string, trips: int, on_time_pct: ?float}>
     */
    public static function byPeriod(array $trips, string $granularity): array
    {
        $buckets = [];
        foreach ($trips as $trip) {
            $at = CarbonImmutable::instance($trip['started_at']);
            $start = $granularity === 'week'
                ? $at->startOfWeek(CarbonInterface::MONDAY)
                : $at->startOfDay();
            $key = $start->format('Y-m-d');

            $buckets[$key] ??= [
                'label' => ($granularity === 'week' ? 'Week of ' : '').$start->format('M j'),
                'trips' => 0, 'on_time' => 0, 'scored' => 0,
            ];
            $buckets[$key]['trips']++;
            if (in_array($trip['punctuality'], ['on_time', 'early', 'late'], true)) {
                $buckets[$key]['scored']++;
            }
            if ($trip['punctuality'] === 'on_time') {
                $buckets[$key]['on_time']++;
            }
        }

        ksort($buckets);

        $rows = [];
        foreach ($buckets as $key => $b) {
            $rows[] = [
                'key' => (string) $key,
                'label' => $b['label'],
                'trips' => $b['trips'],
                'on_time_pct' => ScorecardPolicy::onTimePercent($b['on_time'], $b['scored']),
            ];
        }

        return $rows;
    }
}
