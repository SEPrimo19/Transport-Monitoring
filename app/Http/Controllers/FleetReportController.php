<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Route;
use App\Models\TripMetric;
use App\Models\Vehicle;
use App\Support\AdminContext;
use App\Support\CsvReport;
use App\Support\FleetReportBuilder;
use App\Support\ReportRequest;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The printable vehicles and routes report: how hard each vehicle worked, which active vehicles
 * sat idle, and how each route performed against its own configured duration and its timetable.
 *
 * It names vehicles and routes and never a person. FleetReportIsolationTest fails if this file
 * ever mentions one. Read-only and tenant-only: the routes sit in tenant.admin and every read
 * goes through a model carrying BelongsToOrganization (TripMetric, Vehicle, Route). The figures
 * come from trip_metrics, not raw GPS, so they do not change when the retention purge runs.
 *
 * The page and the CSV both call data(), so they cannot disagree about a number.
 */
class FleetReportController extends Controller
{
    public function report(Request $request, AdminContext $ctx): View
    {
        return view('reports.fleet', $this->data($request) + [
            'organization' => $ctx->organizationId === null ? null : Organization::find($ctx->organizationId),
            'generatedBy' => $ctx->fullName ?: ($ctx->email ?: 'an administrator'),
            'generatedAt' => CarbonImmutable::now(),
        ]);
    }

    /** One CSV carrying the three tables, told apart by the Section column. */
    public function export(Request $request): StreamedResponse
    {
        $data = $this->data($request);

        $header = [
            'Section', 'Item', 'Trips', 'Hours', 'Distance (km)', 'Trips with distance', 'Average duration (min)',
            'Scheduled duration (min)', 'Difference (min)', 'On time %', 'Scored trips', 'No timetable', 'Unscheduled',
        ];

        $rows = static function () use ($data): \Generator {
            foreach ($data['vehicles'] as $v) {
                yield [
                    'By vehicle', $v['label'], $v['trips'], $v['hours'], $v['distance_km'], $v['distance_trips'],
                    null, null, null, $v['on_time_pct'], null, null, null,
                ];
            }
            foreach ($data['idle'] as $i) {
                yield ['Idle vehicle', $i['label'], 0, null, null, null, null, null, null, null, null, null, null];
            }
            foreach ($data['routes'] as $r) {
                yield [
                    'By route', $r['route_code'], $r['trips'], null, null, null, $r['avg_duration_min'],
                    $r['stored_duration_min'], $r['difference_min'], $r['on_time_pct'], $r['scored'],
                    $r['no_timetable'], $r['unscheduled'],
                ];
            }
        };

        return CsvReport::stream(
            'fleet-'.$data['from']->format('Y-m-d').'-to-'.$data['to']->format('Y-m-d').'.csv',
            $header,
            $rows()
        );
    }

    /** @return array<string, mixed> */
    public function data(Request $request): array
    {
        $period = ReportRequest::fromRequest($request, CarbonImmutable::now());

        $base = static fn () => TripMetric::query()
            ->whereBetween('started_at', [$period->from, $period->to])
            ->when($period->routeId, static fn ($q) => $q->where('route_id', $period->routeId));

        $byVehicle = $base()
            ->groupBy('vehicle_id')
            ->selectRaw(
                "vehicle_id,
                 count(*) as trips,
                 coalesce(sum(duration_min), 0) as minutes,
                 sum(distance_km) as distance_km,
                 count(distance_km) as distance_trips,
                 count(*) filter (where punctuality = 'on_time') as on_time,
                 count(*) filter (where punctuality = 'early') as early,
                 count(*) filter (where punctuality = 'late') as late"
            )
            ->get()
            ->map(static function (TripMetric $m): array {
                $a = $m->getAttributes();

                return [
                    'vehicle_id' => $a['vehicle_id'] === null ? null : (string) $a['vehicle_id'],
                    'trips' => (int) $a['trips'],
                    'minutes' => (int) $a['minutes'],
                    'distance_km' => $a['distance_km'] === null ? null : (float) $a['distance_km'],
                    'distance_trips' => (int) $a['distance_trips'],
                    'on_time' => (int) $a['on_time'],
                    'early' => (int) $a['early'],
                    'late' => (int) $a['late'],
                ];
            })->all();

        $byRoute = $base()
            ->groupBy('route_id')
            ->selectRaw(
                "route_id,
                 count(*) as trips,
                 coalesce(sum(duration_min), 0) as minutes,
                 count(*) filter (where punctuality = 'on_time') as on_time,
                 count(*) filter (where punctuality = 'early') as early,
                 count(*) filter (where punctuality = 'late') as late,
                 count(*) filter (where punctuality = 'no_timetable') as no_timetable,
                 count(*) filter (where punctuality = 'unscheduled') as unscheduled"
            )
            ->get()
            ->map(static function (TripMetric $m): array {
                $a = $m->getAttributes();

                return [
                    'route_id' => $a['route_id'] === null ? null : (string) $a['route_id'],
                    'trips' => (int) $a['trips'],
                    'minutes' => (int) $a['minutes'],
                    'on_time' => (int) $a['on_time'],
                    'early' => (int) $a['early'],
                    'late' => (int) $a['late'],
                    'no_timetable' => (int) $a['no_timetable'],
                    'unscheduled' => (int) $a['unscheduled'],
                ];
            })->all();

        $vehicleMap = Vehicle::query()->get(['id', 'fleet_code', 'plate_number', 'status'])
            ->mapWithKeys(static fn (Vehicle $v): array => [
                (string) $v->id => [
                    'fleet_code' => $v->fleet_code === null ? null : (string) $v->fleet_code,
                    'plate' => $v->plate_number === null ? null : (string) $v->plate_number,
                    'status' => (string) $v->status,
                ],
            ])->all();

        $routeMap = Route::query()->orderBy('code')->get(['id', 'code', 'name', 'duration_minutes'])
            ->mapWithKeys(static fn (Route $r): array => [
                (string) $r->id => [
                    'code' => (string) $r->code,
                    'name' => (string) $r->name,
                    'duration_minutes' => $r->duration_minutes === null ? null : (int) $r->duration_minutes,
                ],
            ])->all();

        $vehicles = FleetReportBuilder::vehicles($byVehicle, $vehicleMap);
        $routes = FleetReportBuilder::routes($byRoute, $routeMap);

        // "Idle" only means something across every route: a vehicle with no trips on ONE route
        // may have been busy on another, so a route filter omits the list.
        $routeFiltered = $period->routeId !== null;
        $idle = $routeFiltered
            ? []
            : FleetReportBuilder::idle($vehicleMap, array_values(array_filter(array_column($byVehicle, 'vehicle_id'))));

        $chosen = $period->routeId === null ? null : ($routeMap[$period->routeId] ?? null);

        return [
            'figures' => FleetReportBuilder::figures($vehicles, $idle, $routes),
            'vehicles' => $vehicles,
            'idle' => $idle,
            'routes' => $routes,
            'routeFiltered' => $routeFiltered,
            'from' => $period->from,
            'to' => $period->to,
            'routeLabel' => $chosen === null ? 'All routes' : $chosen['code'].' · '.$chosen['name'],
            'notice' => $period->notice,
            'periodQuery' => $period->query(),
            'timezone' => config('app.timezone'),
        ];
    }
}
