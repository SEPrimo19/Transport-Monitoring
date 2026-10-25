<?php

namespace App\Support;

/**
 * The headline measures of the dashboard summary, assembled from the figures the individual
 * reports already computed.
 *
 * Pure: arrays in, arrays out, and it asks no question of the database. One list of measures
 * feeds both the key-figures strip and the CSV, so the two cannot disagree, and because every
 * value is taken from an individual report's own figures, the summary cannot disagree with them.
 *
 * There is ONE driver-complaint figure, 'Driver-conduct complaints', taken from the complaints
 * report: every conduct report filed in the period. The Driver performance report attributes
 * complaints to named drivers through the trip a report names, so it can show fewer; putting both
 * numbers on one page would be two answers to one question.
 *
 * Every value is a plain number or null. There is no per-person value in the list, on purpose: a
 * board pack states what the cooperative did, not what named people did. A figure nobody
 * measured stays null (never 0), and the idle-vehicle count is null under a route filter,
 * because idleness is only meaningful across every route.
 */
final class SummaryReportBuilder
{
    /**
     * @param  array{figures: array<string, mixed>}  $trips
     * @param  array{figures: array<string, mixed>, byCategory: array<int, array<string, mixed>>}  $complaints
     * @param  array{figures: array<string, mixed>, routeFiltered: bool}  $fleet
     * @param  array{drivers_with_trips: int, trips: int, on_time_pct: ?float}  $drivers
     * @return array<int, array{area: string, measure: string, value: int|float|null}>
     */
    public static function measures(array $trips, array $complaints, array $fleet, array $drivers): array
    {
        $t = $trips['figures'];
        $c = $complaints['figures'];
        $f = $fleet['figures'];

        $conduct = 0;
        foreach ($complaints['byCategory'] as $row) {
            if ($row['scope'] === 'driver') {
                $conduct += (int) $row['received'];
            }
        }

        return [
            self::m('Trips', 'Trips', $t['trips']),
            self::m('Trips', 'Hours in service', $t['hours']),
            self::m('Trips', 'Distance (km)', $t['distance_km']),
            self::m('Trips', 'On time %', $t['on_time_pct']),

            self::m('Complaints', 'Received', $c['received']),
            self::m('Complaints', 'Closed', $c['closed']),
            self::m('Complaints', 'Still open', $c['open']),
            self::m('Complaints', 'Median time to close (minutes)', $c['median_close_minutes']),
            self::m('Complaints', 'Slowest time to close (minutes)', $c['slowest_close_minutes']),
            self::m('Complaints', 'Driver-conduct complaints', $conduct),

            self::m('Fleet', 'Vehicles with trips', $f['vehicles_with_trips']),
            self::m('Fleet', 'Idle vehicles', $fleet['routeFiltered'] ? null : $f['idle_vehicles']),
            self::m('Fleet', 'Routes run', $f['routes_run']),

            self::m('Drivers', 'Drivers with trips', $drivers['drivers_with_trips']),
            self::m('Drivers', 'On time % (all drivers)', $drivers['on_time_pct']),
        ];
    }

    /**
     * One measure's value by area and name, or null when there is no such measure.
     *
     * @param  array<int, array{area: string, measure: string, value: int|float|null}>  $measures
     */
    public static function value(array $measures, string $area, string $measure): int|float|null
    {
        foreach ($measures as $m) {
            if ($m['area'] === $area && $m['measure'] === $measure) {
                return $m['value'];
            }
        }

        return null;
    }

    /**
     * The six figures at the top of the page, in order, taken from the measures.
     *
     * @param  array<int, array{area: string, measure: string, value: int|float|null}>  $measures
     * @return array<int, array{label: string, value: int|float|null}>
     */
    public static function headline(array $measures): array
    {
        $pick = static fn (string $area, string $measure): int|float|null => self::value($measures, $area, $measure);

        return [
            ['label' => 'Trips', 'value' => $pick('Trips', 'Trips')],
            ['label' => 'On time (of scored trips)', 'value' => $pick('Trips', 'On time %')],
            ['label' => 'Hours in service', 'value' => $pick('Trips', 'Hours in service')],
            ['label' => 'Complaints received', 'value' => $pick('Complaints', 'Received')],
            ['label' => 'Complaints still open', 'value' => $pick('Complaints', 'Still open')],
            ['label' => 'Idle vehicles', 'value' => $pick('Fleet', 'Idle vehicles')],
        ];
    }

    /** @return array{area: string, measure: string, value: int|float|null} */
    private static function m(string $area, string $measure, int|float|null $value): array
    {
        return ['area' => $area, 'measure' => $measure, 'value' => $value];
    }
}
