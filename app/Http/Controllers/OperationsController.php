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
            'defaultCenter' => [12.4, 124.55], // central Samar fallback
        ]);
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
