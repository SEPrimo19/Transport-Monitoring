<?php

namespace App\Http\Controllers;

use App\Models\Route;
use App\Models\Trip;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Trip history — read-only browsing of past (and current) trips, org-scoped.
 * The list carries per-status filters and a per-route rollup; the detail shows the
 * trip's recorded GPS trace (location_points) and occupancy timeline.
 */
class TripController extends Controller
{
    public function index(Request $request): View
    {
        // Guard is_string: ?status[]=x arrives as an array and would make
        // array_key_exists() throw a TypeError (uncaught 500).
        $status = $request->query('status');
        if (! is_string($status) || ! array_key_exists($status, Trip::STATUSES)) {
            $status = null;
        }

        $trips = Trip::query()
            ->with(['driver.profile', 'vehicle', 'route'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderByRaw('coalesce(ended_at, started_at, created_at) desc')
            ->paginate(20)
            ->withQueryString();

        // GPS point counts for just this page (no N+1).
        $pointCounts = DB::table('location_points')
            ->whereIn('trip_id', $trips->pluck('id')->all())
            ->select('trip_id', DB::raw('count(*) as c'))
            ->groupBy('trip_id')
            ->pluck('c', 'trip_id');

        $counts = Trip::query()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        // Per-route rollup (over completed trips with timing).
        $routeStats = Trip::query()
            ->when($status, fn ($q) => $q->where('status', $status))
            ->whereNotNull('started_at')->whereNotNull('ended_at')
            ->selectRaw('route_id, count(*) as trips, avg(extract(epoch from (ended_at - started_at))) as avg_secs')
            ->groupBy('route_id')
            ->orderByDesc('trips')
            ->get();
        $routeNames = Route::whereIn('id', $routeStats->pluck('route_id')->all())->pluck('code', 'id');

        return view('trips.index', [
            'trips' => $trips,
            'pointCounts' => $pointCounts,
            'counts' => $counts,
            'activeStatus' => $status,
            'routeStats' => $routeStats,
            'routeNames' => $routeNames,
        ]);
    }

    public function show(Trip $trip): View
    {
        $trip->load(['driver.profile', 'vehicle', 'route']);

        $points = DB::table('location_points')
            ->where('trip_id', $trip->id)
            ->orderBy('recorded_at')
            ->get(['latitude', 'longitude']);

        // Downsample a dense trace for the overview map.
        $coords = $points->map(fn ($p) => [(float) $p->latitude, (float) $p->longitude])->all();
        $n = count($coords);
        if ($n > 1500) {
            $stride = (int) ceil($n / 1500);
            $coords = array_values(array_filter($coords, fn ($c, $i) => $i % $stride === 0, ARRAY_FILTER_USE_BOTH));
        }

        $occupancy = DB::table('occupancy_updates')
            ->where('trip_id', $trip->id)
            ->orderBy('reported_at')
            ->get(['occupancy', 'reported_at']);

        return view('trips.show', [
            'trip' => $trip,
            'trace' => $coords,
            'rawPointCount' => $n,
            'occupancy' => $occupancy,
        ]);
    }
}
