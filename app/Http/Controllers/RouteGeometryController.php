<?php

namespace App\Http\Controllers;

use App\Http\Requests\RouteGeometryRequest;
use App\Models\Route;
use App\Models\RouteGeometry;
use Illuminate\Http\RedirectResponse;
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
        ]);
    }

    public function update(RouteGeometryRequest $request, Route $route): RedirectResponse
    {
        $points = $request->validated()['points'];

        RouteGeometry::updateOrCreate(
            ['route_id' => $route->id],
            [
                'organization_id' => $route->organization_id, // trigger also stamps it
                'points' => $points,
                'point_count' => count($points),
                'source' => 'web_admin',
            ],
        );

        return redirect()->route('routes.geometry.edit', $route)
            ->with('status', 'Polyline saved — '.count($points).' points.');
    }

    public function destroy(Route $route): RedirectResponse
    {
        // Clearing a polyline means removing the row (point_count >= 2 is enforced).
        RouteGeometry::where('route_id', $route->id)->delete();

        return redirect()->route('routes.index')
            ->with('status', "Polyline for \u{201C}{$route->code}\u{201D} was removed.");
    }
}
