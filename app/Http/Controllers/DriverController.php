<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDriverRequest;
use App\Http\Requests\UpdateDriverRequest;
use App\Models\Driver;
use App\Models\Organization;
use App\Models\Profile;
use App\Support\AdminContext;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Driver management via the promote-existing-user model (option A — no
 * service_role, no auth writes). A driver self-registers in the app (RLS forces
 * `passenger`); an admin looks them up by exact email and elevates them to a
 * driver in their org. There is NO passenger directory — the only cross-scope
 * read is the single guarded email lookup in {@see store()}.
 */
class DriverController extends Controller
{
    public function index(AdminContext $ctx): View
    {
        // Org-scoped by the Driver model. profile carries name + account status.
        $drivers = Driver::query()
            ->with('profile')
            ->get()
            ->sortBy(fn ($d) => $d->profile?->full_name)
            ->values();

        // Emails live only in auth.users — one batched lookup, no N+1.
        $emails = $this->emailsFor($drivers->pluck('profile_id')->all());

        // Org names only matter for the super_admin cross-org view.
        $orgNames = $ctx->isSuperAdmin()
            ? Organization::whereIn('id', $drivers->pluck('organization_id')->filter()->unique()->all())
                ->pluck('name', 'id')
            : collect();

        return view('drivers.index', [
            'drivers' => $drivers,
            'emails' => $emails,
            'orgNames' => $orgNames,
        ]);
    }

    public function create(AdminContext $ctx): View
    {
        return view('drivers.create', [
            'organizations' => $this->organizationsFor($ctx),
        ]);
    }

    public function store(StoreDriverRequest $request, AdminContext $ctx): RedirectResponse
    {
        $data = $request->validated();
        $organizationId = $ctx->isSuperAdmin() ? $data['organization_id'] : $ctx->organizationId;

        // Resolve the EXACT email to an account. Unscoped by necessity: passengers
        // are org-less. This is the only place a non-driver profile is touched.
        $authUser = DB::table('auth.users')->where('email', $data['email'])->first(['id']);
        if ($authUser === null) {
            return back()->withInput()->with('error',
                'No account exists for that email. The driver must register in the app first.');
        }

        $profile = Profile::find($authUser->id);
        if ($profile === null) {
            return back()->withInput()->with('error',
                'That account has not finished signing up (no profile yet).');
        }
        if ($profile->role === 'driver') {
            return back()->withInput()->with('error', 'That person is already a driver.');
        }
        if ($profile->role !== 'passenger') {
            return back()->withInput()->with('error', 'Only passenger accounts can be promoted to driver.');
        }
        if ($profile->organization_id !== null && $profile->organization_id !== $organizationId) {
            return back()->withInput()->with('error', 'That account already belongs to another organization.');
        }

        try {
            DB::transaction(function () use ($profile, $data, $organizationId) {
                $profile->update([
                    'role' => 'driver',
                    'organization_id' => $organizationId,
                    'full_name' => $data['full_name'],
                    'is_active' => true,
                ]);

                Driver::create([
                    'profile_id' => $profile->id,
                    'employee_code' => $data['employee_code'],
                    'license_number' => $data['license_number'],
                    'phone' => $data['phone'] ?? null,
                    'is_active' => true,
                    'organization_id' => $organizationId,
                ]);
            });
        } catch (QueryException) {
            return back()->withInput()->with('error',
                'Could not promote this account (it may already be a driver). Please try again.');
        }

        return redirect()->route('drivers.index')
            ->with('status', "{$data['full_name']} is now a driver.");
    }

    public function edit(Driver $driver): View
    {
        $driver->load('profile');

        return view('drivers.edit', [
            'driver' => $driver,
            'email' => $this->emailsFor([$driver->profile_id])[$driver->profile_id] ?? null,
        ]);
    }

    public function update(UpdateDriverRequest $request, Driver $driver): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($driver, $data) {
            $driver->update([
                'employee_code' => $data['employee_code'],
                'license_number' => $data['license_number'],
                'phone' => $data['phone'] ?? null,
                'is_active' => $data['is_active'] ?? false,
            ]);
            $driver->profile()->update(['full_name' => $data['full_name']]);
        });

        return redirect()->route('drivers.index')
            ->with('status', "{$data['full_name']} was updated.");
    }

    public function destroy(Driver $driver): RedirectResponse
    {
        $driver->load('profile');
        $name = $driver->profile?->full_name ?? 'This driver';

        // Guard: cannot remove a driver who is on a scheduled/active trip.
        $onDuty = DB::table('trips')->where('driver_profile_id', $driver->profile_id)
            ->whereIn('status', ['pending', 'active'])->exists();
        if ($onDuty) {
            return redirect()->route('drivers.index')->with('error',
                "{$name} has an active or scheduled trip. End it before removing them.");
        }

        // Remove from fleet = delete the drivers row + revert the person to a plain
        // (org-less) passenger. If they have trip/assignment history the FK RESTRICT
        // blocks the delete → suspend instead.
        try {
            DB::transaction(function () use ($driver) {
                $driver->profile()->update(['role' => 'passenger', 'organization_id' => null]);
                $driver->delete();
            });
        } catch (QueryException) {
            return redirect()->route('drivers.index')->with('error',
                "{$name} has trip history and can't be removed. Suspend them instead (Edit → Suspended).");
        }

        return redirect()->route('drivers.index')->with('status',
            "{$name} was removed from the fleet and reverted to a passenger.");
    }

    /** Batched auth.users email lookup, keyed by profile id. No N+1. */
    private function emailsFor(array $profileIds): Collection
    {
        if (empty($profileIds)) {
            return collect();
        }

        return DB::table('auth.users')->whereIn('id', $profileIds)->pluck('email', 'id');
    }

    /** Org list for the super_admin target-org selector; org_admin doesn't choose. */
    private function organizationsFor(AdminContext $ctx): Collection
    {
        return $ctx->isSuperAdmin()
            ? Organization::orderBy('name')->get(['id', 'name'])
            : collect();
    }
}
