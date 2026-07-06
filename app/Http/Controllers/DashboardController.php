<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Driver;
use App\Models\Organization;
use App\Models\Profile;
use App\Models\Report;
use App\Models\Route;
use App\Models\Terminal;
use App\Models\Vehicle;
use App\Support\AdminContext;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Org-scoped dashboard. Every model here uses OrganizationScope, so an org_admin
 * sees only their own tenant's numbers while a super_admin sees platform-wide
 * totals — the counts double as live proof the tenant scope is active in the UI.
 */
class DashboardController extends Controller
{
    public function index(AdminContext $ctx): View
    {
        $vehicleStatus = Vehicle::query()
            ->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');

        // "Live now" = a fresh position (< 15 min, i.e. not Offline). Age is measured
        // on the DB clock so app-server/cloud clock skew doesn't distort it.
        $liveNow = DB::table('vehicle_live_location')
            ->when(! $ctx->isSuperAdmin(), fn ($q) => $q->where('organization_id', $ctx->organizationId))
            ->whereRaw('extract(epoch from (now() - recorded_at)) < 900')
            ->count();

        $tiles = [
            ['label' => 'Vehicles', 'value' => Vehicle::count(), 'sub' => ($vehicleStatus['active'] ?? 0).' active', 'icon' => 'bus-front', 'href' => route('vehicles.index')],
            ['label' => 'Drivers', 'value' => Driver::count(), 'sub' => 'in your fleet', 'icon' => 'person-badge', 'href' => route('drivers.index')],
            ['label' => 'Terminals', 'value' => Terminal::count(), 'sub' => 'stops', 'icon' => 'pin-map', 'href' => route('terminals.index')],
            ['label' => 'Routes', 'value' => Route::count(), 'sub' => Route::where('is_active', true)->count().' active', 'icon' => 'signpost-2', 'href' => route('routes.index')],
            ['label' => 'Active assignments', 'value' => Assignment::where('is_active', true)->count(), 'sub' => 'driver · vehicle · route', 'icon' => 'clipboard-check', 'href' => route('assignments.index')],
        ];

        if ($ctx->isSuperAdmin()) {
            $tiles[] = ['label' => 'Organizations', 'value' => Organization::count(), 'sub' => 'tenants', 'icon' => 'buildings', 'href' => route('organizations.index')];
            $tiles[] = ['label' => 'Org admins', 'value' => Profile::where('role', 'org_admin')->count(), 'sub' => 'across tenants', 'icon' => 'person-gear', 'href' => route('org-admins.index')];
        }

        return view('dashboard', [
            'tiles' => $tiles,
            'liveNow' => $liveNow,
            'reportsPending' => Report::where('status', 'submitted')->count(),
            'vehicleStatus' => $vehicleStatus,
            'recentReports' => Report::with(['reporter', 'vehicle', 'route'])
                ->orderByDesc('created_at')->limit(6)->get(),
        ]);
    }
}
