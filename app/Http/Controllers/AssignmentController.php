<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignmentRequest;
use App\Models\Assignment;
use App\Models\Driver;
use App\Models\Organization;
use App\Models\Route;
use App\Models\Vehicle;
use App\Support\AdminContext;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Org-scoped assignment management — the driver↔vehicle↔route pairing a driver
 * starts a trip from. This closes the operational chain: create drivers/vehicles/
 * routes → ASSIGN them here → the driver app reads its active assignment. The
 * {assignment} binding and all reads go through OrganizationScope.
 */
class AssignmentController extends Controller
{
    public function index(AdminContext $ctx): View
    {
        $assignments = Assignment::query()
            ->with(['driver.profile', 'vehicle', 'route'])
            ->orderByDesc('is_active')
            ->orderByDesc('starts_at')
            ->get();

        $orgNames = $ctx->isSuperAdmin()
            ? Organization::whereIn('id', $assignments->pluck('organization_id')->filter()->unique()->all())
                ->pluck('name', 'id')
            : collect();

        return view('assignments.index', ['assignments' => $assignments, 'orgNames' => $orgNames]);
    }

    public function create(AdminContext $ctx): View
    {
        return view('assignments.create', [
            'assignment' => new Assignment([
                'is_active' => true,
                'starts_at' => now(),
                'ends_at' => now()->addYear(),
            ]),
        ] + $this->formOptions($ctx));
    }

    public function store(AssignmentRequest $request, AdminContext $ctx): RedirectResponse
    {
        $data = $request->validated();
        $data['created_by_profile_id'] = $ctx->userId; // audit: who assigned

        try {
            Assignment::create($data); // organization_id stamped by the trait
        } catch (QueryException $e) {
            // Safety net for the partial-unique indexes (race with the request check).
            return back()->withInput()->with('error',
                'That driver or vehicle already has an active assignment. Deactivate it first.');
        }

        return redirect()->route('assignments.index')->with('status', 'Assignment was added.');
    }

    public function edit(Assignment $assignment, AdminContext $ctx): View
    {
        return view('assignments.edit', ['assignment' => $assignment] + $this->formOptions($ctx, $assignment));
    }

    public function update(AssignmentRequest $request, Assignment $assignment): RedirectResponse
    {
        // created_by_profile_id is left as the original assigner (not in validated()).
        try {
            $assignment->update($request->validated());
        } catch (QueryException $e) {
            return back()->withInput()->with('error',
                'That driver or vehicle already has an active assignment. Deactivate it first.');
        }

        return redirect()->route('assignments.index')->with('status', 'Assignment was updated.');
    }

    public function destroy(Assignment $assignment): RedirectResponse
    {
        // Guard 1: a driver may be mid-trip on this assignment.
        $inUse = DB::table('trips')->where('assignment_id', $assignment->id)
            ->whereIn('status', ['pending', 'active'])->exists();

        if ($inUse) {
            return redirect()->route('assignments.index')->with('error',
                'This assignment has an active or scheduled trip. End it first, or set the assignment inactive.');
        }

        // Guard 2: FK RESTRICT — an assignment with trip history can't be hard-deleted.
        try {
            $assignment->delete();
        } catch (QueryException $e) {
            return redirect()->route('assignments.index')->with('error',
                'This assignment has trip history and can\u{2019}t be deleted. Set it to Inactive instead.');
        }

        return redirect()->route('assignments.index')->with('status', 'Assignment was deleted.');
    }

    /**
     * Drivers/vehicles/routes/orgs the admin may pair: org-scoped by OrganizationScope.
     *
     * Only ACTIVE entities are offered. A suspended driver, a vehicle in maintenance
     * or a retired route must not be pairable into a new assignment: as of migration
     * 20260814_027 the database refuses to start a trip from one, so listing them here
     * would only manufacture an assignment that fails at the terminal — the worst place
     * to discover it.
     *
     * The assignment being EDITED keeps its own driver/vehicle/route in the list even
     * if one has since been deactivated. Dropping it would silently re-point the
     * assignment at whatever option happened to sort first on the next save.
     */
    private function formOptions(AdminContext $ctx, ?Assignment $assignment = null): array
    {
        return [
            'drivers' => Driver::query()->with('profile')
                ->where(function ($q) use ($assignment) {
                    $q->where('is_active', true);
                    if ($assignment?->driver_profile_id) {
                        $q->orWhere('profile_id', $assignment->driver_profile_id);
                    }
                })
                ->get()
                ->sortBy(fn ($d) => $d->profile?->full_name ?? '')->values(),
            'vehicles' => Vehicle::query()
                ->where(function ($q) use ($assignment) {
                    $q->where('status', 'active');
                    if ($assignment?->vehicle_id) {
                        $q->orWhere('id', $assignment->vehicle_id);
                    }
                })
                ->orderBy('plate_number')->get(),
            'routes' => Route::query()
                ->where(function ($q) use ($assignment) {
                    $q->where('is_active', true);
                    if ($assignment?->route_id) {
                        $q->orWhere('id', $assignment->route_id);
                    }
                })
                ->orderBy('code')->get(),
            'organizations' => $ctx->isSuperAdmin()
                ? Organization::orderBy('name')->get(['id', 'name'])
                : collect(),
            'takenOnRoute' => $this->takenOnRoute($assignment),
        ];
    }

    /**
     * What every driver and vehicle is already committed to, in two shapes.
     *
     * `byRoute` is the BLOCKING view: who cannot be picked for a given route, because
     * uq_assignments_active_driver_route and uq_assignments_active_vehicle_route will
     * refuse it. Keyed by route, and that is the point — a bus on Allen→Calbayog is
     * still free for Calbayog→Allen, which is not an edge case but how a corridor is
     * staffed, and the hint under Status says so. Flagging a bus "already used"
     * outright would hide the second direction.
     *
     * `summary` is the ADVISORY view, and it exists because the blocking view alone
     * made the form look broken. Before a route is chosen there is no clash to report,
     * so every option read as free — including a bus already running both directions,
     * which is as unavailable as a vehicle gets. The summary says what each one is
     * currently doing, so the list is informative from the moment it opens, and the
     * blocking view then disables the specific rows the chosen route rules out.
     *
     * The assignment being edited is excluded from both, or its own row would be
     * reported as a clash with itself.
     *
     * @return array{
     *     byRoute: array<string, array{vehicles: array<string, string>, drivers: array<string, string>}>,
     *     summary: array{vehicles: array<string, string>, drivers: array<string, string>}
     * }
     */
    private function takenOnRoute(?Assignment $assignment): array
    {
        $rows = Assignment::query()
            ->with(['driver.profile', 'vehicle', 'route'])
            ->where('is_active', true)
            ->when($assignment?->getKey(), fn ($q, $id) => $q->whereKeyNot($id))
            ->get(['id', 'route_id', 'vehicle_id', 'driver_profile_id']);

        $byRoute = [];
        $vehicleRoutes = [];
        $driverRoutes = [];

        foreach ($rows as $row) {
            $routeId = (string) $row->route_id;
            $byRoute[$routeId] ??= ['vehicles' => [], 'drivers' => []];

            $driverName = $row->driver?->profile?->full_name ?: 'another driver';
            $plate = $row->vehicle?->plate_number ?: 'another vehicle';
            $routeCode = $row->route?->code ?: 'another route';

            // Each side is labelled with its COUNTERPART: picking a vehicle, you want to
            // know which driver has it, and the reverse. The id alone tells the admin
            // nothing they can act on.
            if ($row->vehicle_id) {
                $byRoute[$routeId]['vehicles'][(string) $row->vehicle_id] = $driverName;
                $vehicleRoutes[(string) $row->vehicle_id][] = $routeCode;
            }
            if ($row->driver_profile_id) {
                $byRoute[$routeId]['drivers'][(string) $row->driver_profile_id] = $plate;
                $driverRoutes[(string) $row->driver_profile_id][] = $routeCode;
            }
        }

        $phrase = static function (array $codes): string {
            $codes = array_values(array_unique($codes));
            sort($codes);

            return 'on '.implode(', ', $codes);
        };

        return [
            'byRoute' => $byRoute,
            'summary' => [
                'vehicles' => array_map($phrase, $vehicleRoutes),
                'drivers' => array_map($phrase, $driverRoutes),
            ],
        ];
    }
}
