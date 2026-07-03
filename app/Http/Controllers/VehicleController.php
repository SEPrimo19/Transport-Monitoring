<?php

namespace App\Http\Controllers;

use App\Http\Requests\VehicleRequest;
use App\Models\Organization;
use App\Models\Vehicle;
use App\Support\AdminContext;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Org-scoped vehicle management. Every read goes through the OrganizationScope
 * (org_admin sees only their fleet; super_admin sees all). Route-model binding
 * for {vehicle} is resolved through the same scope, so an org_admin who guesses
 * another org's vehicle id gets a 404 — isolation on the binding itself.
 */
class VehicleController extends Controller
{
    public function index(AdminContext $ctx): View
    {
        $vehicles = Vehicle::query()
            ->when($ctx->isSuperAdmin(), fn ($q) => $q->with('organization'))
            ->orderBy('plate_number')
            ->get();

        return view('vehicles.index', ['vehicles' => $vehicles]);
    }

    public function create(AdminContext $ctx): View
    {
        return view('vehicles.create', [
            'vehicle' => new Vehicle(['status' => 'active']),
            'organizations' => $this->organizationsFor($ctx),
        ]);
    }

    public function store(VehicleRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $data['status'] === 'active'; // mobile↔web alignment

        // organization_id: for org_admin it is absent here and the trait stamps it;
        // for super_admin it comes from the validated selector.
        $vehicle = Vehicle::create($data);

        return redirect()->route('vehicles.index')
            ->with('status', "Vehicle \u{201C}{$vehicle->plate_number}\u{201D} was added.");
    }

    public function edit(Vehicle $vehicle, AdminContext $ctx): View
    {
        return view('vehicles.edit', [
            'vehicle' => $vehicle,
            'organizations' => $this->organizationsFor($ctx),
        ]);
    }

    public function update(VehicleRequest $request, Vehicle $vehicle): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $data['status'] === 'active';

        $vehicle->update($data);

        return redirect()->route('vehicles.index')
            ->with('status', "Vehicle \u{201C}{$vehicle->plate_number}\u{201D} was updated.");
    }

    public function destroy(Vehicle $vehicle): RedirectResponse
    {
        // Guard 1: never remove a vehicle that is live or has a scheduled/active trip.
        $onDuty = DB::table('vehicle_live_location')->where('vehicle_id', $vehicle->id)->exists()
            || DB::table('trips')->where('vehicle_id', $vehicle->id)
                ->whereIn('status', ['pending', 'active'])->exists();

        if ($onDuty) {
            return redirect()->route('vehicles.index')->with(
                'error',
                "\u{201C}{$vehicle->plate_number}\u{201D} is on an active or scheduled trip. End the trip before removing it."
            );
        }

        // Guard 2: FK RESTRICT — a vehicle with historical trips/points/reports can't
        // be hard-deleted without orphaning history. Deactivating is the right move.
        try {
            $vehicle->delete();
        } catch (QueryException) {
            return redirect()->route('vehicles.index')->with(
                'error',
                "\u{201C}{$vehicle->plate_number}\u{201D} has trip history and can't be deleted. Set its status to Inactive instead."
            );
        }

        return redirect()->route('vehicles.index')
            ->with('status', "Vehicle \u{201C}{$vehicle->plate_number}\u{201D} was deleted.");
    }

    /** Org list for the super_admin tenant selector; org_admin doesn't choose. */
    private function organizationsFor(AdminContext $ctx): Collection
    {
        return $ctx->isSuperAdmin()
            ? Organization::orderBy('name')->get(['id', 'name'])
            : collect();
    }
}
