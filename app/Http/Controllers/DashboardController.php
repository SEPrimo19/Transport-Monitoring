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
use Illuminate\Database\Eloquent\Collection;
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
        $m = $this->metrics($ctx);

        // The view wants a status => count map it can sum() and index by status.
        $vehicleStatus = collect(Vehicle::STATUSES)
            ->mapWithKeys(fn (string $status) => [$status => (int) $m->{'vehicles_'.$status}]);

        $tiles = [
            ['label' => 'Vehicles', 'value' => (int) $m->vehicles, 'sub' => $vehicleStatus['active'].' active', 'icon' => 'bus-front', 'href' => route('vehicles.index')],
            ['label' => 'Drivers', 'value' => (int) $m->drivers, 'sub' => 'in your fleet', 'icon' => 'person-badge', 'href' => route('drivers.index')],
            ['label' => 'Terminals', 'value' => (int) $m->terminals, 'sub' => 'stops', 'icon' => 'pin-map', 'href' => route('terminals.index')],
            ['label' => 'Routes', 'value' => (int) $m->routes, 'sub' => (int) $m->routes_active.' active', 'icon' => 'signpost-2', 'href' => route('routes.index')],
            ['label' => 'Active assignments', 'value' => (int) $m->assignments_active, 'sub' => 'driver · vehicle · route', 'icon' => 'clipboard-check', 'href' => route('assignments.index')],
        ];

        if ($ctx->isSuperAdmin()) {
            $tiles[] = ['label' => 'Organizations', 'value' => (int) $m->organizations, 'sub' => 'tenants', 'icon' => 'buildings', 'href' => route('organizations.index')];
            $tiles[] = ['label' => 'Org admins', 'value' => (int) $m->org_admins, 'sub' => 'across tenants', 'icon' => 'person-gear', 'href' => route('org-admins.index')];
        }

        return view('dashboard', [
            'tiles' => $tiles,
            'liveNow' => (int) $m->live_now,
            'reportsPending' => (int) $m->reports_pending,
            'vehicleStatus' => $vehicleStatus,
            'recentReports' => $this->recentReports(),
        ]);
    }

    /**
     * The six newest reports, decorated in ONE round trip.
     *
     * `->with(['reporter','vehicle','route'])` cost three extra queries to attach
     * three columns to six rows — cheap next to a local database, ~1.3 s against the
     * hosted one. Joining fetches the same labels in the parent query.
     *
     * Reporter identity is still read through Report::reporterDisplayName(), which
     * honours the passenger's anonymity choice; `reporter_name` only feeds it. Do not
     * render that alias directly.
     */
    private function recentReports(): Collection
    {
        return Report::query()
            ->leftJoin('profiles', 'profiles.id', '=', 'reports.passenger_profile_id')
            ->leftJoin('vehicles', 'vehicles.id', '=', 'reports.vehicle_id')
            ->leftJoin('routes', 'routes.id', '=', 'reports.route_id')
            ->selectRaw(
                'reports.*, profiles.full_name as reporter_name,'
                .' vehicles.plate_number as vehicle_plate, routes.code as route_code'
            )
            // Qualified: `created_at` alone is ambiguous once profiles/vehicles/routes
            // are joined in.
            ->orderByDesc('reports.created_at')
            ->limit(6)
            ->get();
    }

    /**
     * Every headline number in ONE round trip.
     *
     * This used to be 8–10 separate `count()` calls. Against a co-located database
     * that is free; against the hosted Supabase instance this app talks to it is not
     * — a round trip measures 370–430 ms from a dev machine, so those counts alone
     * cost 3–4 seconds of a page load that renders in milliseconds. Postgres computes
     * all of them together for barely more than the cost of one.
     *
     * Each metric stays an Eloquent sub-select rather than raw SQL on purpose:
     * OrganizationScope is a GLOBAL scope, and it is applied when the sub-builder is
     * compiled, so every subquery still carries `organization_id = ?` for an org_admin
     * and stays unfiltered for a super_admin. Hand-writing these as raw table reads
     * would silently drop tenant isolation — verify with ->toSql() if you touch this.
     */
    private function metrics(AdminContext $ctx): object
    {
        $countOf = fn ($query) => $query->selectRaw('count(*)');

        $query = DB::query()
            ->selectSub($countOf(Vehicle::query()), 'vehicles')
            ->selectSub($countOf(Driver::query()), 'drivers')
            ->selectSub($countOf(Terminal::query()), 'terminals')
            ->selectSub($countOf(Route::query()), 'routes')
            ->selectSub($countOf(Route::where('is_active', true)), 'routes_active')
            ->selectSub($countOf(Assignment::where('is_active', true)), 'assignments_active')
            ->selectSub($countOf(Report::where('status', 'submitted')), 'reports_pending')
            // "Live now" = a fresh position (< 15 min, i.e. not Offline). Age is measured
            // on the DB clock so app-server/cloud clock skew doesn't distort it.
            // vehicle_live_location has no Eloquent model, so its org filter is by hand.
            ->selectSub(
                DB::table('vehicle_live_location')
                    ->selectRaw('count(*)')
                    ->when(! $ctx->isSuperAdmin(), fn ($q) => $q->where('organization_id', $ctx->organizationId))
                    ->whereRaw('extract(epoch from (now() - recorded_at)) < 900'),
                'live_now'
            );

        foreach (Vehicle::STATUSES as $status) {
            $query->selectSub($countOf(Vehicle::where('status', $status)), 'vehicles_'.$status);
        }

        if ($ctx->isSuperAdmin()) {
            $query->selectSub($countOf(Organization::query()), 'organizations')
                ->selectSub($countOf(Profile::where('role', 'org_admin')), 'org_admins');
        }

        return $query->first();
    }
}
