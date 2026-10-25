<?php

namespace App\Support;

/**
 * The tables of the printed vehicles and routes report.
 *
 * Pure: arrays in, arrays out, no database. A figure nobody measured is null, never 0; a vehicle
 * or route that is no longer on file is folded into one last row rather than shown as several
 * identical dashes; and rows are ordered by label or code, never by how well they did, so the
 * report cannot become a league table of buses.
 *
 * Idle means an ACTIVE vehicle that ran no trip in the period. A vehicle in maintenance, or
 * retired, is expected to be still and is not idle.
 *
 * On time is on_time / (on_time + early + late); trips with no timetable or too far from any
 * scheduled departure are not scored, and are never counted late.
 */
final class FleetReportBuilder
{
    public const UNKNOWN_VEHICLE = 'Vehicle no longer on file';

    public const UNLINKED_ROUTE = 'Not linked to a route';

    private const DASH = '—';

    /**
     * @param  array<int, array<string, mixed>>  $aggregates
     * @param  array<string, array{fleet_code: ?string, plate: ?string, status: string}>  $vehicles
     * @return array<int, array<string, mixed>>
     */
    public static function vehicles(array $aggregates, array $vehicles): array
    {
        $rows = [];
        $unknown = null;

        foreach ($aggregates as $a) {
            $id = $a['vehicle_id'] ?? null;
            $vehicle = $id === null ? null : ($vehicles[$id] ?? null);

            if ($vehicle === null) {
                $unknown = self::mergeVehicle($unknown, $a);

                continue;
            }

            $fleet = (string) ($vehicle['fleet_code'] ?? '');
            $plate = (string) ($vehicle['plate'] ?? '');
            $label = $fleet !== '' ? $fleet : ($plate !== '' ? $plate : self::DASH);

            $rows[] = self::vehicleRow($label, $plate === $label ? '' : $plate, $vehicle['status'], $a);
        }

        usort($rows, static fn (array $x, array $y): int => [mb_strtolower($x['label']), $x['plate']]
            <=> [mb_strtolower($y['label']), $y['plate']]);

        if ($unknown !== null) {
            $rows[] = self::vehicleRow(self::UNKNOWN_VEHICLE, '', '', $unknown);
        }

        return $rows;
    }

    /**
     * @param  array<string, array{fleet_code: ?string, plate: ?string, status: string}>  $vehicles
     * @param  array<int, string>  $vehicleIdsWithTrips
     * @return array<int, array{label: string, plate: string}>
     */
    public static function idle(array $vehicles, array $vehicleIdsWithTrips): array
    {
        $ran = array_flip($vehicleIdsWithTrips);
        $rows = [];

        foreach ($vehicles as $id => $v) {
            if ($v['status'] !== 'active' || isset($ran[$id])) {
                continue;
            }

            $fleet = (string) ($v['fleet_code'] ?? '');
            $plate = (string) ($v['plate'] ?? '');
            $label = $fleet !== '' ? $fleet : ($plate !== '' ? $plate : self::DASH);
            $rows[] = ['label' => $label, 'plate' => $plate === $label ? '' : $plate];
        }

        usort($rows, static fn (array $x, array $y): int => [mb_strtolower($x['label']), $x['plate']]
            <=> [mb_strtolower($y['label']), $y['plate']]);

        return $rows;
    }

    /**
     * @param  array<int, array<string, mixed>>  $aggregates
     * @param  array<string, array{code: string, name: string, duration_minutes: ?int}>  $routes
     * @return array<int, array<string, mixed>>
     */
    public static function routes(array $aggregates, array $routes): array
    {
        $rows = [];
        $unlinked = null;

        foreach ($aggregates as $a) {
            $id = $a['route_id'] ?? null;
            $route = $id === null ? null : ($routes[$id] ?? null);

            if ($route === null) {
                $unlinked = self::mergeRoute($unlinked, $a);

                continue;
            }

            $rows[] = self::routeRow($route['code'], $route['name'], $route['duration_minutes'], $a);
        }

        usort($rows, static fn (array $x, array $y): int => [mb_strtolower($x['route_code']), $x['route_name']]
            <=> [mb_strtolower($y['route_code']), $y['route_name']]);

        if ($unlinked !== null) {
            $rows[] = self::routeRow(self::UNLINKED_ROUTE, '', null, $unlinked);
        }

        return $rows;
    }

    /**
     * @param  array<int, array<string, mixed>>  $vehicleRows
     * @param  array<int, array<string, mixed>>  $idleRows
     * @param  array<int, array<string, mixed>>  $routeRows
     * @return array{trips: int, vehicles_with_trips: int, idle_vehicles: int, routes_run: int}
     */
    public static function figures(array $vehicleRows, array $idleRows, array $routeRows): array
    {
        $trips = 0;
        $withTrips = 0;
        foreach ($vehicleRows as $v) {
            $trips += $v['trips'];
            if ($v['trips'] > 0 && $v['label'] !== self::UNKNOWN_VEHICLE) {
                $withTrips++;
            }
        }

        $routesRun = 0;
        foreach ($routeRows as $r) {
            if ($r['trips'] > 0 && $r['route_code'] !== self::UNLINKED_ROUTE) {
                $routesRun++;
            }
        }

        return [
            'trips' => $trips,
            'vehicles_with_trips' => $withTrips,
            'idle_vehicles' => count($idleRows),
            'routes_run' => $routesRun,
        ];
    }

    /** @param array<string, mixed> $a @return array<string, mixed> */
    private static function vehicleRow(string $label, string $plate, string $status, array $a): array
    {
        return [
            'label' => $label,
            'plate' => $plate,
            'status' => $status,
            'trips' => (int) $a['trips'],
            'hours' => round(((int) $a['minutes']) / 60, 1),
            'distance_km' => $a['distance_km'] === null ? null : round((float) $a['distance_km'], 1),
            'distance_trips' => (int) $a['distance_trips'],
            'on_time_pct' => ScorecardPolicy::onTimePercent((int) $a['on_time'], (int) $a['on_time'] + (int) $a['early'] + (int) $a['late']),
        ];
    }

    /** @param array<string, mixed>|null $into @param array<string, mixed> $a @return array<string, mixed> */
    private static function mergeVehicle(?array $into, array $a): array
    {
        $into ??= ['trips' => 0, 'minutes' => 0, 'distance_km' => null, 'distance_trips' => 0, 'on_time' => 0, 'early' => 0, 'late' => 0];

        foreach (['trips', 'minutes', 'distance_trips', 'on_time', 'early', 'late'] as $k) {
            $into[$k] += (int) $a[$k];
        }
        if ($a['distance_km'] !== null) {
            $into['distance_km'] = ($into['distance_km'] ?? 0.0) + (float) $a['distance_km'];
        }

        return $into;
    }

    /** @param array<string, mixed> $a @return array<string, mixed> */
    private static function routeRow(string $code, string $name, ?int $stored, array $a): array
    {
        $trips = (int) $a['trips'];
        $scored = (int) $a['on_time'] + (int) $a['early'] + (int) $a['late'];
        $avg = $trips === 0 ? null : (int) round(((int) $a['minutes']) / $trips);

        return [
            'route_code' => $code,
            'route_name' => $name,
            'trips' => $trips,
            'avg_duration_min' => $avg,
            'stored_duration_min' => $stored,
            'difference_min' => ($avg === null || $stored === null) ? null : $avg - $stored,
            'on_time_pct' => ScorecardPolicy::onTimePercent((int) $a['on_time'], $scored),
            'scored' => $scored,
            'no_timetable' => (int) $a['no_timetable'],
            'unscheduled' => (int) $a['unscheduled'],
        ];
    }

    /** @param array<string, mixed>|null $into @param array<string, mixed> $a @return array<string, mixed> */
    private static function mergeRoute(?array $into, array $a): array
    {
        $into ??= ['trips' => 0, 'minutes' => 0, 'on_time' => 0, 'early' => 0, 'late' => 0, 'no_timetable' => 0, 'unscheduled' => 0];

        foreach (array_keys($into) as $k) {
            $into[$k] += (int) $a[$k];
        }

        return $into;
    }
}
