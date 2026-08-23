<?php

namespace App\Http\Controllers;

use App\Http\Middleware\AuthenticateSupabaseAdmin;
use App\Http\Requests\CreateOrgAdminLoginRequest;
use App\Http\Requests\ResetOrgAdminPasswordRequest;
use App\Http\Requests\StoreOrgAdminRequest;
use App\Models\Organization;
use App\Models\Profile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Platform-tier management of org_admins — SUPER_ADMIN ONLY (super.admin middleware
 * on the route group). This is the multi-tenant onboarding step: hand a tenant to
 * its administrator, in one of two ways.
 *
 *  1. CREATE A LOGIN OUTRIGHT ({@see storeLogin()}) — a super_admin mints a
 *     brand-new account for a cooperative's office administrator: a full name, a
 *     username, a password, and an OPTIONAL real email. There is no reason an office
 *     administrator would already be a passenger, so requiring one to sign up in the
 *     mobile app first — purely to bring an account into existence that will only
 *     ever be used in this console — was a barrier this removes. Proxies the
 *     `driver-account` Supabase Edge Function's `create_org_admin` action via
 *     {@see OrgAdminAccountProvisioner}.
 *  2. PROMOTE AN EXISTING APP ACCOUNT ({@see store()}, unchanged) — an existing
 *     PASSENGER account is looked up by exact email and elevated. The only
 *     cross-scope read here is that single guarded email lookup.
 *
 * Creating super_admins stays a manual DB step on purpose (bootstrap/safety).
 */
class OrgAdminController extends Controller
{
    public function index(): View
    {
        $admins = Profile::query()->where('role', 'org_admin')->orderBy('full_name')->get();

        $emails = $admins->isNotEmpty()
            ? DB::table('auth.users')->whereIn('id', $admins->pluck('id')->all())->pluck('email', 'id')
            : collect();

        return view('org_admins.index', [
            'admins' => $admins,
            'emails' => $emails,
            'orgNames' => Organization::pluck('name', 'id'),
        ]);
    }

    public function create(): View
    {
        return view('org_admins.create', [
            'organizations' => Organization::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreOrgAdminRequest $request): RedirectResponse
    {
        $data = $request->validated();

        // Resolve the exact account (unscoped — passengers are org-less).
        $user = DB::table('auth.users')->where('email', $data['email'])->first(['id']);
        if ($user === null) {
            return back()->withInput()->with('error',
                'No account exists for that email. Ask the person to sign up in the app first.');
        }

        $profile = DB::table('profiles')->where('id', $user->id)->first(['id', 'role', 'full_name']);
        if ($profile === null) {
            return back()->withInput()->with('error', 'That account has no profile yet.');
        }
        if ($profile->role === 'super_admin') {
            return back()->withInput()->with('error', 'That account is a platform super-admin.');
        }
        if ($profile->role === 'org_admin') {
            return back()->withInput()->with('error', 'That account is already an org admin.');
        }
        if ($profile->role !== 'passenger') {
            return back()->withInput()->with('error',
                'Only a passenger account can be promoted to org admin (this one is a '.$profile->role.').');
        }

        DB::table('profiles')->where('id', $user->id)->update([
            'role' => 'org_admin',
            'organization_id' => $data['organization_id'],
            'full_name' => $data['full_name'] ?: $profile->full_name,
            'is_active' => true,
        ]);

        // The auth middleware caches this row briefly; drop it so the promotion is
        // effective on their very next request rather than up to 30 s later.
        AuthenticateSupabaseAdmin::forgetProfile((string) $user->id);

        return redirect()->route('org-admins.index')
            ->with('status', "{$data['email']} is now an org admin.");
    }

    /** Mint a brand-new org-admin login outright (no self-registration involved). */
    public function storeLogin(CreateOrgAdminLoginRequest $request, OrgAdminAccountProvisioner $provisioner): RedirectResponse
    {
        $data = $request->validated();

        $result = $provisioner->create((string) session('supabase_token'), [
            'organization_id' => $data['organization_id'],
            'full_name' => $data['full_name'],
            'username' => $data['username'],
            'password' => $data['password'],
            'email' => $data['email'],
        ]);

        if (! $result->success) {
            // NEVER a bare withInput() here — see DriverController::storeLogin()'s note.
            // Laravel strips passwords only for VALIDATION exceptions
            // (Handler::$dontFlash); a manual withInput() flashes every field, and
            // sessions are driven to `database` (config/session.php), so that would
            // write the admin-chosen password in plaintext into the sessions table in
            // the shared Supabase Postgres. This path is the COMMON case: it fires
            // whenever the username (or supplied email) is already taken.
            return back()->withInput($request->except('password'))->with('error', $result->message);
        }

        return redirect()->route('org-admins.index')
            ->with('status', "{$data['full_name']} can now sign in as an org admin (\"{$result->email}\").");
    }

    public function destroy(Profile $profile): RedirectResponse
    {
        if ($profile->role !== 'org_admin') {
            return redirect()->route('org-admins.index')->with('error', 'That account is not an org admin.');
        }

        // Deactivate an ADMIN-CREATED login rather than merely demoting it.
        //
        // Demotion alone left the removed person with a working sign-in: the profile stayed
        // active, the password was known only to them, and resetPassword() would no longer
        // touch it because that requires role === 'org_admin'. Passenger-tier only, so
        // nothing elevated survived, but the platform had minted an identity it had no
        // console control to disable.
        //
        // is_active is the right lever because current_profile_id() and current_app_role()
        // both filter on it, so an inactive profile cannot read even its own row and every
        // RLS policy denies it. Deleting the auth user is NOT: reports.resolved_by_profile_id
        // and assignments.created_by_profile_id reference profiles(id) with no ON DELETE
        // clause, and profiles cascades from auth.users, so deleteUser() raises 23503 for
        // any admin who has ever resolved a report or created an assignment. That is most
        // of the people anyone would actually remove.
        //
        // Conditional on purpose. A PROMOTED admin signed up with their own email, and that
        // passenger account is theirs; deactivating it would take away something the
        // cooperative never gave them.
        $email = DB::table('auth.users')->where('id', $profile->getKey())->value('email');
        $isAdminCreated = OrgAdminAccountEmail::isUsernameLogin($email);
        // Captured BEFORE the update below nulls it, or the audit event would have no
        // cooperative and the cooperative could not read its own record of the removal.
        $orgBefore = $profile->organization_id;

        $profile->update([
            'role' => 'passenger',
            'organization_id' => null,
            'is_active' => ! $isAdminCreated,
        ]);

        // Removing an administrator is an accountability event, so it is recorded like the
        // ones the Edge Function writes. This app connects as the database owner and so
        // bypasses RLS, which is why the insert works despite the table having no INSERT
        // policy; that is a property of this deployment, not a licence to write elsewhere.
        //
        // Wrapped so a logging fault can never fail the removal it is recording. An
        // administrator left in place because an audit table was unreachable is worse than
        // a missing row, and the removal has already happened by this point regardless.
        try {
            $actor = app(AdminContext::class);
            DB::table('account_admin_events')->insert([
                'actor_profile_id' => $actor->userId,
                'actor_role' => $actor->role ?? 'unknown',
                'target_profile_id' => $profile->getKey(),
                'target_identifier' => $email,
                'target_kind' => 'org_admin',
                'action' => 'deactivate',
                'organization_id' => $orgBefore,
                'succeeded' => true,
                'detail' => $isAdminCreated
                    ? 'Removed as org admin; admin-created sign-in disabled.'
                    : 'Removed as org admin; own passenger account left active.',
                'occurred_at' => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }

        // Revocation must not wait on a cache TTL — evict immediately so their next
        // request is rejected by the middleware.
        AuthenticateSupabaseAdmin::forgetProfile((string) $profile->getKey());

        return redirect()->route('org-admins.index')
            ->with('status', ($profile->full_name ?: 'The account').($isAdminCreated
                ? ' was removed, and its sign-in was disabled.'
                : ' was removed as an org admin (their own passenger account is unaffected).'));
    }

    /**
     * Reset an org admin's password. Only works for an admin-created login on the
     * `admins.amoectrack.invalid` domain — the Edge Function re-derives the real
     * email server-side and refuses otherwise, exactly like DriverController's.
     */
    public function resetPassword(ResetOrgAdminPasswordRequest $request, Profile $profile, OrgAdminAccountProvisioner $provisioner): RedirectResponse
    {
        if ($profile->role !== 'org_admin') {
            return redirect()->route('org-admins.index')->with('error', 'That account is not an org admin.');
        }

        $data = $request->validated();

        $result = $provisioner->resetPassword((string) session('supabase_token'), (string) $profile->getKey(), $data['password']);

        if (! $result->success) {
            return redirect()->route('org-admins.index')->with('error', $result->message);
        }

        return redirect()->route('org-admins.index')
            ->with('status', ($profile->full_name ?: 'The account').'\'s password was reset.');
    }
}
