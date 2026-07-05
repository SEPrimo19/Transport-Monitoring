<?php

namespace App\Http\Controllers;

use App\Http\Requests\RouteGeometryRequest;
use App\Models\Route;
use App\Models\RouteGeometry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * Map editor for a route's polyline (route_geometry). Belongs to the ORG-ADMIN
 * Routes screen — a route's geometry is that org's operational data. The {route}
 * binding is org-scoped (OrganizationScope), so an org_admin can only reach their
 * own routes; a cross-org id 404s. The polyline is stored/read as [[lat,lng],...]
 * to match the mobile overlay; the editor round-trips that order exactly.
 */
class RouteGeometryController extends Controller
{
    public function edit(Route $route): View
    {
        $route->load(['originTerminal', 'destinationTerminal', 'geometry']);

        $terminalPoint = static function ($t): ?array {
            if (! $t || $t->latitude === null || $t->longitude === null) {
                return null;
            }

            return ['lat' => (float) $t->latitude, 'lng' => (float) $t->longitude, 'code' => $t->code];
        };

        return view('routes.geometry', [
            'route' => $route,
            'points' => $route->geometry?->points,          // [[lat,lng],...] or null
            'origin' => $terminalPoint($route->originTerminal),
            'dest' => $terminalPoint($route->destinationTerminal),
            'defaultCenter' => [12.4, 124.55],              // central Samar fallback
            'tripTrace' => $this->latestTripTrace($route),  // real driven path or null
        ]);
    }

    /**
     * The most recent ENDED trip on this route that has a usable GPS trace — the
     * highest-accuracy source for BOTH the polyline (the actual road driven) and the
     * route's real distance/duration. Returns downsampled [[lat,lng],...] plus the
     * trip's real distance (haversine over the trace) and duration (ended-started).
     * Org-safe: the route is already org-bound and trips are filtered by it.
     *
     * @return array{points:array<int,array{0:float,1:float}>, raw_count:int, recorded_on:?string, distance_km:float, duration_minutes:?int}|null
     */
    private function latestTripTrace(Route $route): ?array
    {
        $trip = DB::table('trips as t')
            ->join('location_points as lp', 'lp.trip_id', '=', 't.id')
            ->where('t.route_id', $route->id)
            ->where('t.status', 'ended')
            ->groupBy('t.id', 't.started_at', 't.ended_at')
            ->havingRaw('count(lp.id) >= 2')
            ->orderByDesc('t.ended_at')
            ->select('t.id', 't.started_at', 't.ended_at')
            ->first();

        if (! $trip) {
            return null;
        }

        $rows = DB::table('location_points')
            ->where('trip_id', $trip->id)
            ->orderBy('recorded_at')
            ->get(['latitude', 'longitude']);

        $full = $rows->map(fn ($p) => [(float) $p->latitude, (float) $p->longitude])->all();

        $distanceKm = round($this->traceDistanceKm($full), 2);

        // A stationary / degenerate trace (e.g. a QA trip that never actually moved,
        // where every point is the same spot) isn't a usable route path — and a 0 km
        // distance would fail the apply-metrics validation. Don't offer it.
        if ($distanceKm < 0.1) {
            return null;
        }

        $durationMinutes = null;
        if ($trip->started_at && $trip->ended_at) {
            $secs = strtotime($trip->ended_at) - strtotime($trip->started_at);
            $durationMinutes = $secs >= 60 ? (int) round($secs / 60) : null;
        }

        return [
            'points' => $this->downsample($full, 2000),
            'raw_count' => count($full),
            'recorded_on' => $trip->ended_at,
            'distance_km' => $distanceKm,
            'duration_minutes' => $durationMinutes,
        ];
    }

    /** Even-stride downsample of a [[lat,lng],...] list, always keeping the last point. */
    private function downsample(array $coords, int $max): array
    {
        $n = count($coords);
        if ($n <= $max) {
            return $coords;
        }

        $stride = (int) ceil($n / $max);
        $out = [];
        foreach ($coords as $i => $c) {
            if ($i % $stride === 0) {
                $out[] = $c;
            }
        }
        if (end($out) !== $coords[$n - 1]) {
            $out[] = $coords[$n - 1];
        }

        return $out;
    }

    /** Great-circle length (km) of a [[lat,lng],...] path. */
    private function traceDistanceKm(array $coords): float
    {
        $r = 6371.0;
        $sum = 0.0;
        for ($i = 1, $c = count($coords); $i < $c; $i++) {
            $lat1 = deg2rad($coords[$i - 1][0]);
            $lat2 = deg2rad($coords[$i][0]);
            $dLat = $lat2 - $lat1;
            $dLng = deg2rad($coords[$i][1] - $coords[$i - 1][1]);
            $a = sin($dLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dLng / 2) ** 2;
            $sum += $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
        }

        return $sum;
    }

    public function update(RouteGeometryRequest $request, Route $route): RedirectResponse
    {
        $points = $request->validated()['points'];

        // Optional: also write the generated distance (accurate — road length) and
        // duration (a driving estimate) onto the route. Validated up-front so a bad
        // value never leaves the geometry saved but the metrics half-applied.
        $metrics = null;
        if ($request->filled('apply_distance_km')) {
            $metrics = Validator::make(
                $request->only('apply_distance_km', 'apply_duration_minutes'),
                [
                    'apply_distance_km' => ['numeric', 'min:0.01', 'max:99999.99'],
                    'apply_duration_minutes' => ['nullable', 'integer', 'min:1', 'max:100000'],
                ],
            )->validate();
        }

        RouteGeometry::updateOrCreate(
            ['route_id' => $route->id],
            [
                'organization_id' => $route->organization_id, // trigger also stamps it
                'points' => $points,
                'point_count' => count($points),
                'source' => 'web_admin',
            ],
        );

        if ($metrics !== null) {
            $route->update([
                'distance_km' => $metrics['apply_distance_km'],
                'duration_minutes' => $metrics['apply_duration_minutes'] ?? $route->duration_minutes,
            ]);
        }

        $message = $metrics !== null
            ? 'Polyline saved ('.count($points).' points); route distance & duration updated.'
            : 'Polyline saved — '.count($points).' points.';

        return redirect()->route('routes.geometry.edit', $route)->with('status', $message);
    }

    public function destroy(Route $route): RedirectResponse
    {
        // Clearing a polyline means removing the row (point_count >= 2 is enforced).
        RouteGeometry::where('route_id', $route->id)->delete();

        return redirect()->route('routes.index')
            ->with('status', "Polyline for \u{201C}{$route->code}\u{201D} was removed.");
    }
}
