<?php

namespace App\Http\Controllers;

use App\Http\Requests\OrganizationRequest;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Platform-tier tenant management — super_admin ONLY (enforced by the
 * `super.admin` middleware on the route group). Organizations are the tenant root
 * and are NOT org-scoped. There is intentionally NO delete: removing a tenant
 * would violate FK RESTRICT across every dependent table (profiles, vehicles,
 * routes, trips, reports…). Deactivation is `status = 'suspended'` instead.
 */
class OrganizationController extends Controller
{
    public function index(): View
    {
        $organizations = Organization::query()->orderBy('name')->get();

        // Tenant size at a glance — one grouped query, no N+1.
        $vehicleCounts = DB::table('vehicles')
            ->select('organization_id', DB::raw('count(*) as c'))
            ->groupBy('organization_id')
            ->pluck('c', 'organization_id');

        $driverCounts = DB::table('drivers')
            ->select('organization_id', DB::raw('count(*) as c'))
            ->groupBy('organization_id')
            ->pluck('c', 'organization_id');

        return view('organizations.index', [
            'organizations' => $organizations,
            'vehicleCounts' => $vehicleCounts,
            'driverCounts' => $driverCounts,
        ]);
    }

    public function create(): View
    {
        return view('organizations.create', [
            'organization' => new Organization(['status' => 'active']),
        ]);
    }

    public function store(OrganizationRequest $request): RedirectResponse
    {
        $org = Organization::create($request->validated());

        return redirect()->route('organizations.index')
            ->with('status', "Organization \u{201C}{$org->name}\u{201D} was created.");
    }

    public function edit(Organization $organization): View
    {
        return view('organizations.edit', ['organization' => $organization]);
    }

    public function update(OrganizationRequest $request, Organization $organization): RedirectResponse
    {
        // validated() omits slug on update (immutable) — it's preserved untouched.
        $organization->update($request->validated());

        return redirect()->route('organizations.index')
            ->with('status', "Organization \u{201C}{$organization->name}\u{201D} was updated.");
    }
}
