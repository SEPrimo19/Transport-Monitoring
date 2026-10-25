<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Route;
use App\Models\TripMetric;
use App\Support\AdminContext;
use App\Support\CsvReport;
use App\Support\ReportRequest;
use App\Support\TripReportBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The printable trips report: how many trips a cooperative ran in a period, how long they took,
 * how many left on time, and how that splits by route and by day or week.
 *
 * Read-only and tenant-only. The routes sit in the tenant.admin group, and every read goes
 * through a model carrying BelongsToOrganization (TripMetric, Route). The numbers come from
 * trip_metrics, not raw GPS, so they do not change when the retention purge runs.
 *
 * The page and the CSV both call data(), so they cannot disagree about a figure.
 */
class TripReportController extends Controller
{
    public function report(Request $request, AdminContext $ctx): View
    {
        $data = $this->data($request);

        return view('trips.report', $data + [
            'organization' => $ctx->organizationId === null ? null : Organization::find($ctx->organizationId),
            'generatedBy' => $ctx->fullName ?: ($ctx->email ?: 'an administrator'),
            'generatedAt' => CarbonImmutable::now(),
        ]);
    }

    /**
     * One CSV carrying both tables. The Section column says which table a row belongs to, so a
     * single download holds the by-route and the by-day (or by-week) figures. Cells that do not
     * apply to a row are empty, and a figure nobody measured is empty, never 0.
     */
    public function export(Request $request): StreamedResponse
    {
        $data = $this->data($request);

        $header = [
            'Section', 'Period or route', 'Trips', 'Average duration (min)', 'On time', 'Early', 'Late',
            'No timetable', 'Unscheduled', 'On time %', 'Distance (km)', 'Trips with distance',
        ];
        $periodSection = $data['granularity'] === 'week' ? 'By week' : 'By day';

        $rows = static function () use ($data, $periodSection): \Generator {
            foreach ($data['byRoute'] as $r) {
                yield [
                    'By route', $r['route_code'], $r['trips'], $r['avg_duration_min'], $r['on_time'], $r['early'],
                    $r['late'], $r['no_timetable'], $r['unscheduled'], $r['on_time_pct'], $r['distance_km'],
                    $r['distance_trips'],
                ];
            }
            foreach ($data['byPeriod'] as $p) {
                yield [
                    $periodSection, $p['key'], $p['trips'], null, null, null, null, null, null, $p['on_time_pct'], null, null,
                ];
            }
        };

        return CsvReport::stream(
            'trips-'.$data['from']->format('Y-m-d').'-to-'.$data['to']->format('Y-m-d').'.csv',
            $header,
            $rows()
        );
    }

    /** @return array<string, mixed> */
    private function data(Request $request): array
    {
        $period = ReportRequest::fromRequest($request, CarbonImmutable::now());

        $base = static fn () => TripMetric::query()
            ->whereBetween('started_at', [$period->from, $period->to])
            ->when($period->routeId, static fn ($q) => $q->where('route_id', $period->routeId));

        $aggregates = $base()
            ->groupBy('route_id')
            ->selectRaw(
                "route_id,
                 count(*) as trips,
                 coalesce(sum(duration_min), 0) as minutes,
                 sum(distance_km) as distance_km,
                 count(distance_km) as distance_trips,
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
                    'distance_km' => $a['distance_km'] === null ? null : (float) $a['distance_km'],
                    'distance_trips' => (int) $a['distance_trips'],
                    'on_time' => (int) $a['on_time'],
                    'early' => (int) $a['early'],
                    'late' => (int) $a['late'],
                    'no_timetable' => (int) $a['no_timetable'],
                    'unscheduled' => (int) $a['unscheduled'],
                ];
            })->all();

        // Days and weeks are bucketed in PHP, in the application timezone: date_trunc is
        // Postgres-only and these tests run on SQLite. Two light columns per trip.
        $tz = config('app.timezone');
        $trips = $base()
            ->get(['started_at', 'punctuality'])
            ->map(static fn (TripMetric $m): array => [
                'started_at' => CarbonImmutable::parse((string) $m->getAttributes()['started_at'])->setTimezone($tz),
                'punctuality' => (string) $m->getAttributes()['punctuality'],
            ])->all();

        $allRoutes = Route::query()->orderBy('code')->get(['id', 'code', 'name']);
        $routes = $allRoutes->mapWithKeys(static fn (Route $r): array => [
            (string) $r->id => ['code' => (string) $r->code, 'name' => (string) $r->name],
        ])->all();
        $chosen = $period->routeId === null ? null : ($routes[$period->routeId] ?? null);

        $granularity = TripReportBuilder::granularity($period->from, $period->to);

        return [
            'figures' => TripReportBuilder::figures($aggregates),
            'byRoute' => TripReportBuilder::byRoute($aggregates, $routes),
            'byPeriod' => TripReportBuilder::byPeriod($trips, $granularity),
            'granularity' => $granularity,
            'from' => $period->from,
            'to' => $period->to,
            'routeLabel' => $chosen === null ? 'All routes' : $chosen['code'].' · '.$chosen['name'],
            'notice' => $period->notice,
            'periodQuery' => $period->query(),
            'timezone' => $tz,
        ];
    }
}
