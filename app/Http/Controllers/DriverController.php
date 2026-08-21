<?php

namespace App\Http\Controllers;

use App\Http\Middleware\AuthenticateSupabaseAdmin;
use App\Http\Requests\CreateDriverLoginRequest;
use App\Http\Requests\ResetDriverPasswordRequest;
use App\Http\Requests\StoreDriverRequest;
use App\Http\Requests\UpdateDriverRequest;
use App\Models\Driver;
use App\Models\Organization;
use App\Models\Profile;
use App\Support\AdminContext;
use App\Support\DriverAccountEmail;
use App\Support\DriverAccountProvisioner;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Driver management: two ways to get a driver into the roster.
 *
 *  1. CREATE A LOGIN (default; {@see storeLogin()}) — an admin mints a driver
 *     account outright: a username (stored as `employee_code`, which becomes
 *     `<code>@drivers.amoectrack.invalid`), full name, license number and a
 *     password the admin chooses. For drivers with no Gmail or low tech
 *     confidence, self-registration in the app is a barrier this removes.
 *  2. PROMOTE AN EXISTING APP ACCOUNT ({@see store()}, unchanged) — a driver
 *     who already self-registered in the app (RLS forces `passenger`) is
 *     looked up by exact email and elevated. The only cross-scope read here
 *     is that single guarded email lookup.
 *
 * This app still holds NO service_role key (see config/supabase.php) — so
 * unlike its old docblock claimed, this controller DOES now trigger an
 * `auth.users` write (path 1, password resets, and {@see destroy()} releasing
 * an admin-created login's username), just never with a key it holds itself.
 * That write happens inside the `driver-account` Supabase Edge Function
 * (Android repo: supabase/functions/driver-account), reached only through
 * {@see DriverAccountProvisioner}, authenticated as the SIGNED-IN admin's own
 * session token.
 */
class DriverController extends Controller
{
    public function index(AdminContext $ctx): View
    {
        // Org-scoped by the Driver model. profile carries name + account status.
        $drivers = Driver::query()
            ->with('profile')
            ->leftJoin('auth.users', 'auth.users.id', '=', 'drivers.profile_id')
            ->select('drivers.*', 'auth.users.email as account_email')
            ->get()
            ->sortBy(fn ($d) => $d->profile?->full_name)
            ->values();

        // Emails live only in auth.users. Read in the SAME round trip as the list rather
        // than a second batched one: the query count was never the problem here (it was
        // already 1), but each round trip to the hosted database costs ~450 ms from this
        // machine, so halving them halves the page's time to first byte.
        $emails = $drivers->pluck('account_email', 'profile_id')->filter();

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

    /** Mint a brand-new driver login (no self-registration involved). */
    public function storeLogin(CreateDriverLoginRequest $request, AdminContext $ctx, DriverAccountProvisioner $provisioner): RedirectResponse
    {
        $data = $request->validated();
        $organizationId = $ctx->isSuperAdmin() ? $data['organization_id'] : $ctx->organizationId;

        $result = $provisioner->create((string) session('supabase_token'), [
            'organization_id' => $organizationId,
            'full_name' => $data['full_name'],
            'employee_code' => $data['employee_code'],
            'license_number' => $data['license_number'],
            'password' => $data['password'],
        ]);

        if (! $result->success) {
            // NEVER a bare withInput() here. Laravel strips passwords only for VALIDATION
            // exceptions (Handler::$dontFlash); a manual withInput() flashes every field.
            // Sessions are driven to `database` (config/session.php), so a bare call wrote
            // the admin-chosen driver password in plaintext into the sessions table in the
            // shared Supabase Postgres — and from there into backups and WAL. This path is
            // the COMMON case: it fires whenever the username is already taken.
            return back()->withInput($request->except('password'))->with('error', $result->message);
        }

        return redirect()->route('drivers.index')
            ->with('status', "{$data['full_name']} can now sign in as \"{$data['employee_code']}\".");
    }

    public function edit(Driver $driver): View
    {
        $driver->load('profile');
        $email = $this->emailsFor([$driver->profile_id])[$driver->profile_id] ?? null;

        return view('drivers.edit', [
            'driver' => $driver,
            'email' => $email,
            // Admin-created login (username baked into the GoTrue email at creation)
            // vs promoted from a real personal email. Drives the read-only
            // employee_code field and the reset-password copy below.
            'isUsernameLogin' => DriverAccountEmail::isUsernameLogin($email),
        ]);
    }

    /**
     * `is_active` is mirrored onto `drivers` AND `profiles` in the same transaction.
     * `drivers.is_active` alone was never a real kill switch: it blocks trip start and
     * hides the roster row, but `current_profile_id()` / `current_app_role()` (the RLS
     * predicates every other table's policies are built on) only check
     * `profiles.is_active` — see migration 020's schema RLS. A driver suspended here but
     * left active on `profiles` keeps a valid credential with driver-level read access
     * to tenant data. This applies to every driver, including one promoted from a
     * self-registered app account — see the Suspended help text on the edit view for why
     * that's a deliberate, not accidental, consequence.
     */
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
            $driver->profile()->update([
                'full_name' => $data['full_name'],
                'is_active' => $data['is_active'] ?? false,
            ]);
        });

        // The auth middleware caches this row (see AuthenticateSupabaseAdmin) — evict it
        // so a suspension is effective on the driver's very next request rather than up
        // to the cache TTL later. Harmless no-op if the driver never held an admin
        // session (drivers don't use the web admin), cheap either way.
        AuthenticateSupabaseAdmin::forgetProfile((string) $driver->profile_id);

        return redirect()->route('drivers.index')
            ->with('status', "{$data['full_name']} was updated.");
    }

    /**
     * Reset a driver's password. Works whether their login was admin-created
     * or promoted from a self-registered app account — either way it's the
     * same auth.users row. {@see Driver} is org-scoped, so the {driver} route
     * binding already fails a cross-org attempt before this method runs; the
     * Edge Function checks it again server-side.
     */
    public function resetPassword(ResetDriverPasswordRequest $request, Driver $driver, DriverAccountProvisioner $provisioner): RedirectResponse
    {
        $data = $request->validated();

        $result = $provisioner->resetPassword((string) session('supabase_token'), (string) $driver->profile_id, $data['password']);

        if (! $result->success) {
            return back()->with('error', $result->message);
        }

        return redirect()->route('drivers.edit', $driver)
            ->with('status', 'The password was reset.');
    }

    public function destroy(Driver $driver, DriverAccountProvisioner $provisioner): RedirectResponse
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

        // Same condition the FK RESTRICT on assignments/trips/occupancy_updates →
        // drivers would enforce, checked up front rather than only in the catch below.
        // The login-release call after this point is NOT part of the DB transaction —
        // it talks to a different system (GoTrue) Postgres can't roll back — so
        // discovering only afterwards that the roster row can't be deleted would mean
        // a working driver's login was released while they're still listed as active.
        // That is worse than the bug being fixed.
        $hasHistory = DB::table('assignments')->where('driver_profile_id', $driver->profile_id)->exists()
            || DB::table('trips')->where('driver_profile_id', $driver->profile_id)->exists()
            || DB::table('occupancy_updates')->where('driver_profile_id', $driver->profile_id)->exists();
        if ($hasHistory) {
            return redirect()->route('drivers.index')->with('error',
                "{$name} has trip history and can't be removed. Suspend them instead (Edit → Suspended) — ".
                'that revokes their access to sign in while keeping their history.');
        }

        // Release the <code>@drivers.amoectrack.invalid username BEFORE touching this
        // app's own rows. The Edge Function re-derives the account's real email
        // itself (never trusts this app) and is a no-op for a promoted driver's
        // personal address — see delete_account in the driver-account Edge Function.
        $result = $provisioner->deleteAccount((string) session('supabase_token'), (string) $driver->profile_id);
        if (! $result->success) {
            return redirect()->route('drivers.index')->with('error', $result->message);
        }

        // Remove from fleet = delete the drivers row + revert the person to a plain
        // (org-less) passenger. An FK violation (23503) here should be nearly
        // impossible given the pre-check above — kept as a defensive backstop for a
        // row inserted in the gap between that check and this delete, and the message
        // for it is worded for that case. Anything else landing in this catch (most
        // likely a dropped connection) is a DIFFERENT failure: the login was already
        // released above, so telling the admin to "suspend instead" would be actively
        // wrong — there's no login left to suspend.
        try {
            DB::transaction(function () use ($driver) {
                $driver->profile()->update(['role' => 'passenger', 'organization_id' => null]);
                $driver->delete();
            });
        } catch (QueryException $e) {
            if ($e->getCode() === '23503') {
                return redirect()->route('drivers.index')->with('error',
                    "{$name} has trip history and can't be removed. Suspend them instead (Edit → Suspended) — ".
                    'that revokes their access to sign in while keeping their history.');
            }

            return redirect()->route('drivers.index')->with('error',
                "{$name}'s login was removed, but the roster could not be updated. Refresh the driver list and check.");
        }

        $status = $result->accountDeleted
            ? "{$name}'s login was removed and the username released."
            : "{$name} was removed from the fleet and reverted to a passenger.";

        return redirect()->route('drivers.index')->with('status', $status);
    }

    /** Batched auth.users email lookup, keyed by profile id. No N+1. */
    /**
     * Emails for a set of drivers, keyed by profile id.
     *
     * Kept as a separate round trip ONLY where the caller already has the drivers in
     * memory. On the index this is folded into the list query instead — see index() —
     * because against the hosted database every round trip costs ~450 ms, and a page
     * that issues three of them spends well over a second before it renders a thing.
     */
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
