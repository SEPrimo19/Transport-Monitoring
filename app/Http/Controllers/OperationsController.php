<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Support\AdminContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Live operations map — the last-known position of every vehicle that has been
 * transmitting, org-scoped. Read-only monitoring: the page polls the JSON `data`
 * endpoint every few seconds rather than wiring Supabase Realtime into the browser
 * (the mobile passenger app owns the sub-second path; a short poll is plenty here).
 * Freshness mirrors the passenger confidence model so a bus in a dead zone or a
 * stale row reads as such instead of looking live.
 */
class OperationsController extends Controller
{
    public function index(AdminContext $ctx): View
    {
        $vehicles = $this->liveVehicles($ctx);

        return view('operations.index', [
            'vehicles' => $vehicles,
            'corridors' => $this->corridors($ctx),
            'defaultCenter' => [12.4, 124.55], // central Samar fallback
        ]);
    }

    /**
     * The org's active routes, for the corridor filter above the map.
     *
     * Read from the routes table rather than named in the view, so this holds for any
     * cooperative: one corridor gives one chip, six give six. Each route carries the
     * bounding box of its stored polyline, which lets the map frame a corridor that
     * currently has no vehicle on it. Only the box is sent, not the polyline itself —
     * a saved route can run to 2000 points and none of them are needed here.
     *
     * @return array<int, array{code:string, name:string, bounds:?array{0:array{0:float,1:float},1:array{0:float,1:float}}}>
     */
    private function corridors(AdminContext $ctx): array
    {
        $rows = DB::table('routes as r')
            ->leftJoin('route_geometry as g', 'g.route_id', '=', 'r.id')
            ->where('r.is_active', true)
            ->when(! $ctx->isSuperAdmin(), fn ($q) => $q->where('r.organization_id', $ctx->organizationId))
            ->orderBy('r.code')
            ->get(['r.code', 'r.name', 'g.points']);

        return $rows->map(function ($row) {
            $points = is_string($row->points) ? json_decode($row->points, true) : $row->points;
            $bounds = null;

            if (is_array($points) && $points !== []) {
                $lats = array_column($points, 0);
                $lngs = array_column($points, 1);
                if ($lats !== [] && $lngs !== []) {
                    $bounds = [[min($lats), min($lngs)], [max($lats), max($lngs)]];
                }
            }

            return ['code' => $row->code, 'name' => $row->name, 'bounds' => $bounds];
        })->all();
    }

    public function data(AdminContext $ctx): JsonResponse
    {
        return response()->json(['vehicles' => $this->liveVehicles($ctx)]);
    }

    /**
     * Current vehicle positions (org-scoped) with a freshness classification.
     *
     * @return array<int, array<string, mixed>>
     */
    private function liveVehicles(AdminContext $ctx): array
    {
        $rows = DB::table('vehicle_live_location as vll')
            ->join('vehicles as v', 'v.id', '=', 'vll.vehicle_id')
            ->leftJoin('routes as r', 'r.id', '=', 'vll.route_id')
            ->leftJoin('trips as t', 't.id', '=', 'vll.trip_id')
            ->leftJoin('profiles as p', 'p.id', '=', 't.driver_profile_id')
            // Live Ops = vehicles on an ACTIVE trip only. vehicle_live_location keeps
            // a vehicle's LAST position after its trip ends, so without this filter
            // long-ended trips linger forever as grey "Offline" markers. An active
            // trip that's gone quiet still shows (classified Stale/Offline) — that's
            // the dead-zone signal we want; a cleanly ended trip correctly drops off.
            ->where('t.status', 'active')
            ->when(! $ctx->isSuperAdmin(), fn ($q) => $q->where('vll.organization_id', $ctx->organizationId))
            ->orderByDesc('vll.recorded_at')
            ->get([
                'vll.vehicle_id', 'vll.organization_id', 'vll.latitude', 'vll.longitude',
                'vll.speed_mps', 'vll.heading_deg', 'vll.recorded_at',
                'v.plate_number', 'v.fleet_code',
                'r.code as route_code', 'r.name as route_name',
                'p.full_name as driver_name', 't.status as trip_status',
                // Age computed on the DB clock — recorded_at comes from the DB, and
                // the app server's clock may differ, which would skew a PHP-side diff.
                DB::raw('extract(epoch from (now() - vll.recorded_at)) as age_seconds'),
            ]);

        $orgNames = $ctx->isSuperAdmin() ? Organization::pluck('name', 'id') : collect();

        return $rows->map(function ($row) use ($ctx, $orgNames) {
            $age = (int) max(0, round((float) $row->age_seconds));
            [$level, $color] = $this->classify($age);

            return [
                'vehicle_id' => $row->vehicle_id,
                'plate' => $row->plate_number,
                'fleet_code' => $row->fleet_code,
                'route' => $row->route_code,
                'route_name' => $row->route_name,
                'driver' => $row->driver_name,
                'trip_active' => $row->trip_status === 'active',
                'lat' => (float) $row->latitude,
                'lng' => (float) $row->longitude,
                'speed_kmh' => $row->speed_mps !== null ? (int) round($row->speed_mps * 3.6) : null,
                'heading' => $row->heading_deg !== null ? (float) $row->heading_deg : null,
                'age_seconds' => $age,
                'age_label' => $this->humanizeAge($age),
                'level' => $level,
                'color' => $color,
                'org' => $ctx->isSuperAdmin() ? ($orgNames[$row->organization_id] ?? null) : null,
            ];
        })->all();
    }

    /** Freshness buckets mirroring PassengerMapViewModel.computeConfidenceLevel. */
    private function classify(int $age): array
    {
        return match (true) {
            $age <= 30 => ['Live', '#198754'],       // green
            $age <= 120 => ['Recent', '#20c997'],    // teal
            $age <= 300 => ['Delayed', '#ffc107'],   // amber
            $age <= 900 => ['Stale', '#fd7e14'],     // orange
            default => ['Offline', '#6c757d'],        // grey
        };
    }

    private function humanizeAge(int $seconds): string
    {
        return match (true) {
            $seconds < 60 => $seconds.'s ago',
            $seconds < 3600 => intdiv($seconds, 60).'m ago',
            $seconds < 86400 => intdiv($seconds, 3600).'h ago',
            default => intdiv($seconds, 86400).'d ago',
        };
    }
}
