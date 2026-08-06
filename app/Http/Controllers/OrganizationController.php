<?php

namespace App\Http\Controllers;

use App\Http\Requests\OrganizationRequest;
use App\Models\Organization;
use App\Providers\AppServiceProvider;
use App\Support\OrgLogoUploader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
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
        // Logo upload is an EDIT-only action (needs the org id to scope the storage
        // path), so create ignores any logo_file and only the Logo URL text field
        // applies here.
        $org = Organization::create(Arr::except($request->validated(), ['logo_file']));

        return redirect()->route('organizations.index')
            ->with('status', "Organization \u{201C}{$org->name}\u{201D} was created.");
    }

    public function edit(Organization $organization): View
    {
        return view('organizations.edit', ['organization' => $organization]);
    }

    public function update(OrganizationRequest $request, Organization $organization, OrgLogoUploader $logos): RedirectResponse
    {
        // validated() omits slug on update (immutable) — it's preserved untouched.
        $data = Arr::except($request->validated(), ['logo_file']);

        // A chosen file overrides the Logo URL text field. super_admin may write
        // any org's folder (enforced by the bucket RLS in migration 025).
        // "Remove logo" (when no new file is chosen) clears it back to the mark.
        if ($request->hasFile('logo_file')) {
            try {
                $data['logo_url'] = $logos->upload(
                    $organization->id,
                    $request->file('logo_file'),
                    (string) $request->session()->get('supabase_token')
                );
            } catch (\RuntimeException $e) {
                return back()->withInput()->with('error', $e->getMessage());
            }
        } elseif ($request->boolean('remove_logo')) {
            $logos->deleteIfOwned($organization->logo_url, (string) $request->session()->get('supabase_token'));
            $data['logo_url'] = null;
        }

        $organization->update($data);

        // Its org_admins render the sidebar from a cached copy of this row.
        Cache::forget(AppServiceProvider::orgChromeCacheKey((string) $organization->getKey()));

        return redirect()->route('organizations.index')
            ->with('status', "Organization \u{201C}{$organization->name}\u{201D} was updated.");
    }
}
