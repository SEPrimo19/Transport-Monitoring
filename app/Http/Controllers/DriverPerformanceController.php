<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use App\Models\Organization;
use App\Models\Report;
use App\Models\Route;
use App\Models\TripMetric;
use App\Support\AdminContext;
use App\Support\ScorecardBuilder;
use App\Support\ScorecardPolicy;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

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
     * Everything the page and, later, the export need, in one place so the two cannot disagree.
     *
     * @return array<string, mixed>
     */
    private function scorecard(Request $request, AdminContext $ctx): array
    {
        $period = ScorecardPolicy::resolvePeriod(
            $this->queryString($request, 'preset'),
            $this->queryString($request, 'from'),
            $this->queryString($request, 'to'),
            CarbonImmutable::now()
        );
        $from = $period['from'];
        $to = $period['to'];

        $routeId = $this->routeFilter($this->queryString($request, 'route'));

        $drivers = Driver::query()->with('profile')->get()
            ->map(static fn (Driver $d): array => [
                'profile_id' => (string) $d->profile_id,
                'name' => $d->profile?->full_name,
                'employee_code' => $d->employee_code,
                'is_active' => (bool) $d->is_active,
            ])->all();

        $aggregates = TripMetric::query()
            ->whereBetween('started_at', [$from, $to])
            ->when($routeId, static fn ($q) => $q->where('route_id', $routeId))
            ->groupBy('driver_profile_id')
            ->selectRaw(
                "driver_profile_id,
                 count(*) as trips,
                 coalesce(sum(duration_min), 0) as minutes,
                 coalesce(sum(distance_km), 0) as distance_km,
                 count(*) filter (where punctuality = 'on_time') as on_time,
                 count(*) filter (where punctuality = 'early') as early,
                 count(*) filter (where punctuality = 'late') as late,
                 count(*) filter (where punctuality = 'no_timetable') as no_timetable,
                 count(*) filter (where punctuality = 'unscheduled') as unscheduled,
                 count(*) filter (where data_quality = 'partial') as partial,
                 avg(avg_moving_speed_kmh) as avg_speed_kmh,
                 max(max_speed_kmh) as max_speed_kmh,
                 avg(overspeed_share) as overspeed_share"
            )
            ->get()
            ->mapWithKeys(static function (TripMetric $m): array {
                $a = $m->getAttributes();

                return [(string) $a['driver_profile_id'] => [
                    'trips' => (int) $a['trips'],
                    'minutes' => (int) $a['minutes'],
                    'distance_km' => (float) $a['distance_km'],
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
            'preset' => $this->queryString($request, 'preset'),
            'routeId' => $routeId,
            'routes' => Route::query()->orderBy('code')->get(['id', 'code', 'name']),
            'unattributed' => $unattributed,
            'lastComputed' => $lastComputed === null ? null : Carbon::parse($lastComputed),
            // The signed-in cooperative's own setting. NULL means not set, and is shown as such.
            'overspeedLimit' => $ctx->organizationId === null
                ? null
                : Organization::find($ctx->organizationId)?->overspeed_limit_kmh,
            'minTripsForRate' => ScorecardPolicy::MIN_TRIPS_FOR_RATE,
        ];
    }

    /** A query value that is a plain string, or null. `?from[]=x` arrives as an array. */
    private function queryString(Request $request, string $key): ?string
    {
        $value = $request->query($key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * The route filter, only if it is a UUID that resolves through the scoped Route model. A
     * malformed value would otherwise reach Postgres as an invalid uuid and become a 500, and
     * another cooperative's route id is simply not found.
     */
    private function routeFilter(?string $routeId): ?string
    {
        if ($routeId === null || ! Str::isUuid($routeId)) {
            return null;
        }

        return Route::query()->whereKey($routeId)->exists() ? $routeId : null;
    }
}
