<?php

namespace App\Http\Controllers;

use App\Http\Requests\RouteRequest;
use App\Models\Organization;
use App\Models\Route;
use App\Models\Terminal;
use App\Support\AdminContext;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Org-scoped route management (metadata + terminal refs + distance/duration). The
 * polyline itself (route_geometry, [lat,lng]) is shown read-only here; editing it
 * needs a map editor (Step 3). Reads/binding go through OrganizationScope.
 */
class RouteController extends Controller
{
    public function index(AdminContext $ctx): View
    {
        $routes = Route::query()
            ->with(['originTerminal', 'destinationTerminal', 'geometry'])
            ->orderBy('code')
            ->get();

        $orgNames = $ctx->isSuperAdmin()
            ? Organization::whereIn('id', $routes->pluck('organization_id')->filter()->unique()->all())
                ->pluck('name', 'id')
            : collect();

        return view('routes.index', ['routes' => $routes, 'orgNames' => $orgNames]);
    }

    public function create(AdminContext $ctx): View
    {
        return view('routes.create', [
            'route' => new Route(['is_active' => true]),
            'terminals' => $this->terminalsFor($ctx),
            'organizations' => $this->organizationsFor($ctx),
        ]);
    }

    public function store(RouteRequest $request): RedirectResponse
    {
        // organization_id: absent for org_admin (trait stamps it); set from the
        // validated selector for super_admin.
        $route = Route::create($request->validated());

        return redirect()->route('routes.index')
            ->with('status', "Route \u{201C}{$route->code}\u{201D} was added.");
    }

    public function edit(Route $route, AdminContext $ctx): View
    {
        $route->load('geometry');

        return view('routes.edit', [
            'route' => $route,
            'terminals' => $this->terminalsFor($ctx),
            'organizations' => $this->organizationsFor($ctx),
        ]);
    }

    public function update(RouteRequest $request, Route $route): RedirectResponse
    {
        $route->update($request->validated());

        return redirect()->route('routes.index')
            ->with('status', "Route \u{201C}{$route->code}\u{201D} was updated.");
    }

    public function destroy(Route $route): RedirectResponse
    {
        // Guard 1: never remove a route that is in use by a scheduled/active trip
        // (or a live vehicle currently on it).
        $inUse = DB::table('trips')->where('route_id', $route->id)
                ->whereIn('status', ['pending', 'active'])->exists()
            || DB::table('vehicle_live_location')->where('route_id', $route->id)->exists();

        if ($inUse) {
            return redirect()->route('routes.index')->with('error',
                "\u{201C}{$route->code}\u{201D} is in use by an active or scheduled trip. End it first.");
        }

        // Guard 2: FK RESTRICT — a route with trip/assignment/report history can't be
        // hard-deleted (route_geometry cascades, but those don't). Deactivate instead.
        try {
            $route->delete();
        } catch (QueryException) {
            return redirect()->route('routes.index')->with('error',
                "\u{201C}{$route->code}\u{201D} has trip history and can't be deleted. Set it to Inactive instead.");
        }

        return redirect()->route('routes.index')
            ->with('status', "Route \u{201C}{$route->code}\u{201D} was deleted.");
    }

    /** Terminals the admin may attach: org-scoped (org_admin own, super_admin all). */
    private function terminalsFor(AdminContext $ctx): Collection
    {
        return Terminal::query()->orderBy('code')->get(['id', 'code', 'name', 'organization_id']);
    }

    private function organizationsFor(AdminContext $ctx): Collection
    {
        return $ctx->isSuperAdmin()
            ? Organization::orderBy('name')->get(['id', 'name'])
            : collect();
    }
}
