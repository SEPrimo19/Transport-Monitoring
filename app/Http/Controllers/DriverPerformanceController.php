<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use App\Models\Organization;
use App\Models\Report;
use App\Models\Route;
use App\Models\TripMetric;
use App\Support\AdminContext;
use App\Support\CsvReport;
use App\Support\DriverReportSummary;
use App\Support\ReportRequest;
use App\Support\ScorecardBuilder;
use App\Support\ScorecardPolicy;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Driver performance for the signed-in cooperative: trips, punctuality, driving indicators and
 * misconduct complaints per driver, over a chosen period.
 *
 * This is HR data about named people, so three things hold throughout:
 *
 *  - TENANT ISOLATION. The route sits in the tenant.admin group, so a super_admin never reaches
 *    it, and every read goes through a model carrying BelongsToOrganization. The one join, from
 *    reports to trip_metrics, is also pinned to the signed-in organization explicitly, because a
 *    join onto another table does not inherit the scope of the model being queried.
 *    DriverPerformanceIsolationTest bans every way around it.
 *  - NO VERDICTS. There is no composite score, no ranking and no default order other than the
 *    driver's name. The rules about what counts live in ScorecardPolicy and ScorecardBuilder.
 *  - READ-ONLY. Nothing here writes. The numbers come from trip_metrics, which the database
 *    fills hourly and which, unlike raw GPS, does not change when the retention purge runs.
 *
 * Profile is never queried directly: it carries no organization scope by design (passengers are
 * org-less), so names arrive only through Driver::profile(), whose parent is scoped.
 */
class DriverPerformanceController extends Controller
{
    public function index(Request $request, AdminContext $ctx): View
    {
        return view('analytics.drivers.index', $this->scorecard($request, $ctx));
    }

    /**
     * The same rows as the page, as a CSV, with the same filters and the same scoping, because
     * it calls the same scorecard(). Two copies of the query would eventually disagree about a
     * number that a person is judged by.
     *
     * Every cell goes through CsvSanitizer: a driver name is typed by a person, and a name that
     * begins with = or @ would otherwise be a formula in the spreadsheet that opens this file.
     * Missing values are blank cells, never 0, for the same reason the page shows a dash.
     *
     * The UTF-8 byte order mark is what makes Excel read Filipino names correctly.
     */
    public function export(Request $request, AdminContext $ctx): StreamedResponse
    {
        $card = $this->scorecard($request, $ctx);

        $filename = 'driver-performance-'.$card['from']->format('Y-m-d').'-to-'.$card['to']->format('Y-m-d').'.csv';

        $header = [
            'Driver', 'Employee code', 'Trips', 'Hours', 'Distance (km)',
            'On time %', 'Scored trips', 'Early', 'Late', 'No timetable', 'Unscheduled',
            'Avg speed (km/h)', 'Top speed (km/h)', 'Over-speed share %', 'Partial-data trips',
            'Complaints', 'Received', 'Under review', 'Upheld', 'Not upheld', 'Outcome not recorded',
            'Dismissed', 'Safety-related', 'Complaints per 100 trips', 'Anonymous share %',
        ];

        // A limit that is not set means over-speed was not measured, so the column is blank.
        $limitSet = $card['overspeedLimit'] !== null;

        $rows = static function () use ($card, $limitSet): \Generator {
            foreach ($card['rows'] as $r) {
                $c = $r['complaints'];

                yield [
                    $r['name'], $r['employee_code'], $r['trips'], $r['hours'], $r['distance_km'],
                    $r['on_time_pct'], $r['scored'], $r['early'], $r['late'], $r['no_timetable'], $r['unscheduled'],
                    $r['avg_speed_kmh'], $r['max_speed_kmh'],
                    $limitSet && $r['overspeed_share'] !== null ? round($r['overspeed_share'] * 100, 1) : null,
                    $r['partial'],
                    $c['total'], $c['received'], $c['under_review'], $c['upheld'], $c['not_upheld'],
                    $c['outcome_not_recorded'], $c['dismissed'], $r['safety_related'], $r['rate_per_100'],
                    $r['anonymous_share'] === null ? null : round($r['anonymous_share'] * 100, 1),
                ];
            }
        };

        return CsvReport::stream($filename, $header, $rows());
    }

    /**
     * The printable report: the scorecard rows, the headline figures and a driver-by-route
     * table, on the shared report layout. It calls scorecard(), so it cannot disagree with the
     * screen or the CSV about a number, and it adds one more aggregate: how each driver did on
     * each route, so a driver who works two corridors is not averaged into one figure.
     */
    public function report(Request $request, AdminContext $ctx): View
    {
        // `?driver=` narrows the report to one person, for a conversation with them. It is
        // resolved through the scoped Driver model, so another cooperative's driver is a 404.
        $driverParam = $request->query('driver');
        $driverId = null;
        $driverName = null;
        if ($driverParam !== null) {
            abort_unless(is_string($driverParam) && Str::isUuid($driverParam), 404);
            $driver = Driver::query()->with('profile')->whereKey(strtolower($driverParam))->firstOrFail();
            $driverId = (string) $driver->profile_id;
            $driverName = $driver->profile?->full_name ?: ($driver->employee_code ?: 'Unnamed driver');
        }

        $card = $this->scorecard($request, $ctx, $driverId);

        $aggregates = TripMetric::query()
            ->whereBetween('started_at', [$card['from'], $card['to']])
            ->when($driverId, static fn ($q) => $q->where('driver_profile_id', $driverId))
            ->when($card['routeId'], static fn ($q) => $q->where('route_id', $card['routeId']))
            ->groupBy('driver_profile_id', 'route_id')
            ->selectRaw(
                "driver_profile_id, route_id,
                 count(*) as trips,
                 count(*) filter (where punctuality = 'on_time') as on_time,
                 count(*) filter (where punctuality in ('on_time', 'early', 'late')) as scored"
            )
            ->get()
            ->map(static function (TripMetric $m): array {
                $a = $m->getAttributes();

                return [
                    'driver_id' => (string) $a['driver_profile_id'],
                    'route_id' => $a['route_id'] === null ? null : (string) $a['route_id'],
                    'trips' => (int) $a['trips'],
                    'on_time' => (int) $a['on_time'],
                    'scored' => (int) $a['scored'],
                ];
            })->all();

        $codes = Route::query()
            ->whereIn('id', array_values(array_filter(array_column($aggregates, 'route_id'))))
            ->pluck('code', 'id')
            ->all();

        $chosen = $card['routeId'] === null ? null : $card['routes']->firstWhere('id', $card['routeId']);

        return view('analytics.drivers.report', $card + [
            'figures' => DriverReportSummary::figures($card['rows']),
            'perRoute' => DriverReportSummary::perRoute($card['rows'], $aggregates, $codes),
            'routeLabel' => $chosen === null ? 'All routes' : $chosen->code.' · '.$chosen->name,
            'driverId' => $driverId,
            'driverName' => $driverName,
            'organization' => $ctx->organizationId === null ? null : Organization::find($ctx->organizationId),
            'generatedBy' => $ctx->fullName ?: ($ctx->email ?: 'an administrator'),
            'generatedAt' => CarbonImmutable::now(),
            'periodQuery' => array_filter([
                'from' => $card['from']->format('Y-m-d'),
                'to' => $card['to']->format('Y-m-d'),
                'route' => $card['routeId'],
            ]),
        ]);
    }

    /**
     * One driver page: their summary, how they did on each route, their trips, and the
     * complaints behind the count, each one linking to the report itself.
     *
     * `{driver}` is bound through the Driver model, so OrganizationScope runs before this method
     * does and another cooperative driver is a 404, not a page. DriverPerformanceIsolationTest
     * pins the type-hint, because dropping it would quietly turn this into a cross-tenant read.
     *
     * The reporter is deliberately NOT shown here. This page is about the driver; who complained
     * is for the administrator on the report itself, and a figure about a driver sitting beside
     * the complainant name, masked or not, is the wrong place for it.
     */
    public function show(Driver $driver, Request $request, AdminContext $ctx): View
    {
        return view('analytics.drivers.show', $this->detail($driver, $request, $ctx));
    }

    /**
     * Everything one driver's performance page shows, in one place so the page and the driver
     * profile cannot disagree. The complaints join below is pinned to the signed-in organization
     * and exists nowhere else: keep it that way.
     *
     * `$recentTrips` null paginates the period's trips (the performance page); an int returns just
     * that many, newest first, as a plain collection (the profile).
     *
     * @return array<string, mixed>
     */
    public function detail(Driver $driver, Request $request, AdminContext $ctx, ?int $recentTrips = null): array
    {
        $id = (string) $driver->profile_id;
        $card = $this->scorecard($request, $ctx, $id);
        $from = $card['from'];
        $to = $card['to'];
        $routeId = $card['routeId'];

        // An inactive driver with no trips is dropped from the list, but opening their page
        // directly should still work, so the row is built from the driver itself in that case.
        $row = $card['rows'][0] ?? ScorecardBuilder::build([[
            'profile_id' => $id,
            'name' => $driver->profile?->full_name,
            'employee_code' => $driver->employee_code,
            'is_active' => true,
        ]], [], [])[0];

        $periodTrips = TripMetric::query()
            ->where('driver_profile_id', $id)
            ->whereBetween('started_at', [$from, $to])
            ->when($routeId, static fn ($q) => $q->where('route_id', $routeId));

        $perRoute = (clone $periodTrips)
            ->groupBy('route_id')
            ->selectRaw(
                "route_id,
                 count(*) as trips,
                 count(*) filter (where punctuality = 'on_time') as on_time,
                 count(*) filter (where punctuality in ('on_time', 'early', 'late')) as scored,
                 avg(avg_moving_speed_kmh) as avg_speed_kmh"
            )
            ->get();

        $codes = Route::query()->whereIn('id', $perRoute->pluck('route_id')->filter()->all())->pluck('code', 'id');

        $byRoute = $perRoute->map(static function (TripMetric $m) use ($codes): array {
            $a = $m->getAttributes();

            return [
                'route_id' => $a['route_id'],
                'code' => $codes[$a['route_id']] ?? '—',
                'trips' => (int) $a['trips'],
                'on_time_pct' => ScorecardPolicy::onTimePercent((int) $a['on_time'], (int) $a['scored']),
                'avg_speed_kmh' => $a['avg_speed_kmh'] === null ? null : round((float) $a['avg_speed_kmh']),
            ];
        })->sortBy('code')->values()->all();

        // The complaints behind the count. Conduct and safety only: a vehicle or route report is
        // not about this driver. Pinned to this organization like the scorecard own join.
        $complaints = Report::query()
            ->join('trip_metrics as m', 'm.trip_id', '=', 'reports.trip_id')
            ->where('m.organization_id', $ctx->organizationId)
            ->where('m.driver_profile_id', $id)
            ->whereBetween('m.started_at', [$from, $to])
            ->when($routeId, static fn ($q) => $q->where('m.route_id', $routeId))
            ->whereIn('reports.category', ['driver_behavior', 'safety'])
            ->orderByDesc('reports.created_at')
            ->get(['reports.*']);

        return [
            'driver' => $driver,
            'row' => $row,
            'from' => $from,
            'to' => $to,
            'routeId' => $routeId,
            'byRoute' => $byRoute,
            'trips' => $recentTrips === null
                ? (clone $periodTrips)->with('route')->orderByDesc('started_at')->paginate(25)->withQueryString()
                : (clone $periodTrips)->with('route')->orderByDesc('started_at')->limit($recentTrips)->get(),
            'notice' => $card['notice'],
            'preset' => $card['preset'],
            'complaints' => $complaints,
            'overspeedLimit' => $card['overspeedLimit'],
            'minTripsForRate' => $card['minTripsForRate'],
            'periodQuery' => array_filter([
                'from' => $from->format('Y-m-d'),
                'to' => $to->format('Y-m-d'),
                'route' => $routeId,
            ]),
        ];
    }

    /**
     * Everything the page and, later, the export need, in one place so the two cannot disagree.
     *
     * @return array<string, mixed>
     */
    public function scorecard(Request $request, AdminContext $ctx, ?string $driverId = null): array
    {
        $period = ReportRequest::fromRequest($request, CarbonImmutable::now());
        $from = $period->from;
        $to = $period->to;
        $routeId = $period->routeId;

        $drivers = Driver::query()->with('profile')
            ->when($driverId, static fn ($q) => $q->whereKey($driverId))
            ->get()
            ->map(static fn (Driver $d): array => [
                'profile_id' => (string) $d->profile_id,
                'name' => $d->profile?->full_name,
                'employee_code' => $d->employee_code,
                'is_active' => (bool) $d->is_active,
            ])->all();

        // The signed-in cooperative's own setting. NULL means not set, and is shown as such.
        $overspeedLimit = $ctx->organizationId === null
            ? null
            : Organization::find($ctx->organizationId)?->overspeed_limit_kmh;

        $aggregates = TripMetric::query()
            ->whereBetween('started_at', [$from, $to])
            ->when($driverId, static fn ($q) => $q->where('driver_profile_id', $driverId))
            ->when($routeId, static fn ($q) => $q->where('route_id', $routeId))
            ->groupBy('driver_profile_id')
            ->selectRaw(
                "driver_profile_id,
                 count(*) as trips,
                 coalesce(sum(duration_min), 0) as minutes,
                 sum(distance_km) as distance_km,
                 count(*) filter (where punctuality = 'on_time') as on_time,
                 count(*) filter (where punctuality = 'early') as early,
                 count(*) filter (where punctuality = 'late') as late,
                 count(*) filter (where punctuality = 'no_timetable') as no_timetable,
                 count(*) filter (where punctuality = 'unscheduled') as unscheduled,
                 count(*) filter (where data_quality = 'partial') as partial,
                 avg(avg_moving_speed_kmh) as avg_speed_kmh,
                 max(max_speed_kmh) as max_speed_kmh,
                 avg(overspeed_share) filter (where overspeed_limit_kmh_used = ?) as overspeed_share",
                // Only trips computed against the limit the page names are averaged. NULL matches nothing.
                [$overspeedLimit]
            )
            ->get()
            ->mapWithKeys(static function (TripMetric $m): array {
                $a = $m->getAttributes();

                return [(string) $a['driver_profile_id'] => [
                    'trips' => (int) $a['trips'],
                    'minutes' => (int) $a['minutes'],
                    'distance_km' => $a['distance_km'] === null ? null : (float) $a['distance_km'],
                    'on_time' => (int) $a['on_time'],
                    'early' => (int) $a['early'],
                    'late' => (int) $a['late'],
                    'no_timetable' => (int) $a['no_timetable'],
                    'unscheduled' => (int) $a['unscheduled'],
                    'partial' => (int) $a['partial'],
                    'avg_speed_kmh' => $a['avg_speed_kmh'] === null ? null : round((float) $a['avg_speed_kmh'], 1),
                    'max_speed_kmh' => $a['max_speed_kmh'] === null ? null : round((float) $a['max_speed_kmh'], 1),
                    'overspeed_share' => $a['overspeed_share'] === null ? null : round((float) $a['overspeed_share'], 3),
                ]];
            })->all();

        // A report belongs to a driver through its trip. The join is pinned to this organization
        // as well as relying on Report's own scope (see the class docblock).
        $reports = Report::query()
            ->join('trip_metrics as m', 'm.trip_id', '=', 'reports.trip_id')
            ->where('m.organization_id', $ctx->organizationId)
            ->whereBetween('m.started_at', [$from, $to])
            ->when($driverId, static fn ($q) => $q->where('m.driver_profile_id', $driverId))
            ->when($routeId, static fn ($q) => $q->where('m.route_id', $routeId))
            ->get([
                'm.driver_profile_id as driver_id',
                'reports.category',
                'reports.status',
                'reports.resolution_outcome as outcome',
                'reports.is_anonymous',
            ])
            ->map(static fn (Report $r): array => $r->getAttributes())
            ->all();

        // Conduct reports that name no trip cannot be given to any driver, and are not guessed
        // onto one. They are counted so the page can say how many were left out.
        $unattributed = Report::query()
            ->whereNull('trip_id')
            ->whereIn('category', ['driver_behavior', 'safety'])
            ->whereBetween('created_at', [$from, $to])
            ->when($routeId, static fn ($q) => $q->where('route_id', $routeId))
            ->count();

        $lastComputed = TripMetric::query()->max('computed_at');

        return [
            'rows' => ScorecardBuilder::build($drivers, $aggregates, $reports),
            'from' => $from,
            'to' => $to,
            'preset' => $period->preset,
            'notice' => $period->notice,
            'routeId' => $routeId,
            'routes' => Route::query()->orderBy('code')->get(['id', 'code', 'name']),
            'unattributed' => $unattributed,
            'lastComputed' => $lastComputed === null ? null : Carbon::parse($lastComputed),
            'overspeedLimit' => $overspeedLimit,
            'minTripsForRate' => ScorecardPolicy::MIN_TRIPS_FOR_RATE,
        ];
    }
}
