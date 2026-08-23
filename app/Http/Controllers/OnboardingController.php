<?php

namespace App\Http\Controllers;

use App\Http\Requests\OnboardingRequest;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Onboarding wizard — super_admin ONLY (super.admin middleware on the route).
 * Answers "how does a new cooperative join?" in ONE flow instead of two
 * disconnected steps: it creates the tenant AND promotes its first org_admin
 * together, in a single transaction.
 *
 * The first admin must ALREADY have a passenger account in the app, mirroring the
 * promote-by-email model in OrgAdminController. The passenger checks run BEFORE anything
 * is written, so a bad email never leaves a half-created org.
 *
 * That restriction is now a GAP rather than a constraint. It once read "we can't mint
 * auth users here — there is no service_role key on purpose", which is no longer true:
 * the driver-account Edge Function mints them via OrgAdminAccountProvisioner, and
 * OrgAdminController already offers it. Onboarding simply was not extended, so a brand-new
 * cooperative still needs its first admin to install the passenger app and sign up.
 *
 * The workaround is two screens instead of one (Organizations, then Org Admins → Create a
 * new login). To close it, mirror the two-tab form here and call the provisioner OUTSIDE
 * the transaction, after the org is committed: the org must exist before the function
 * inserts profiles.organization_id, and a GoTrue call is a different system that Postgres
 * cannot roll back. Same reasoning as DriverController::destroy().
 */
class OnboardingController extends Controller
{
    public function create(): View
    {
        return view('onboarding.create', [
            'organization' => new Organization(['status' => 'active']),
        ]);
    }

    public function store(OnboardingRequest $request): RedirectResponse
    {
        $data = $request->validated();

        // ── Resolve + vet the first admin BEFORE creating anything ──────────
        $user = DB::table('auth.users')->where('email', $data['admin_email'])->first(['id']);
        if ($user === null) {
            return back()->withInput()->with('error',
                'No account exists for '.$data['admin_email'].'. Ask them to sign up in the app first, then onboard.');
        }

        $profile = DB::table('profiles')->where('id', $user->id)->first(['id', 'role', 'full_name']);
        if ($profile === null) {
            return back()->withInput()->with('error', 'That account has no profile yet.');
        }
        if ($profile->role === 'super_admin') {
            return back()->withInput()->with('error', 'That account is a platform super-admin and can\'t be a tenant admin.');
        }
        if ($profile->role === 'org_admin') {
            return back()->withInput()->with('error', 'That account already administers another organization.');
        }
        if ($profile->role !== 'passenger') {
            return back()->withInput()->with('error',
                'Only a passenger account can become the first org admin (this one is a '.$profile->role.').');
        }

        // ── Create the tenant + promote the admin atomically ────────────────
        $orgFields = Arr::except($data, ['admin_email', 'admin_full_name']);

        $org = DB::transaction(function () use ($orgFields, $data, $profile, $user) {
            // Set the id in PHP so we know it for the promotion (a DB-default UUID
            // isn't read back for a non-incrementing key).
            $org = new Organization($orgFields);
            $org->id = (string) Str::uuid();
            $org->save();

            DB::table('profiles')->where('id', $user->id)->update([
                'role' => 'org_admin',
                'organization_id' => $org->id,
                'full_name' => $data['admin_full_name'] ?: $profile->full_name,
                'is_active' => true,
            ]);

            return $org;
        });

        return redirect()->route('organizations.index')
            ->with('status', "Onboarded \u{201C}{$org->name}\u{201D} — {$data['admin_email']} is now its org admin.");
    }
}
