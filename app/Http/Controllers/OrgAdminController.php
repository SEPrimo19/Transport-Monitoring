<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrgAdminRequest;
use App\Models\Organization;
use App\Models\Profile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Platform-tier management of org_admins — SUPER_ADMIN ONLY (super.admin middleware
 * on the route group). A super_admin promotes an existing PASSENGER account (by
 * exact email) to org_admin of a chosen organization, and can demote one back to a
 * passenger. This is the multi-tenant onboarding step: hand a tenant to its admin.
 *
 * Mirrors the driver promote-by-email flow: the only cross-scope read is the single
 * guarded email lookup + the (super-admin-only) list of org_admins. Creating
 * super_admins stays a manual DB step on purpose (bootstrap/safety).
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

        return redirect()->route('org-admins.index')
            ->with('status', "{$data['email']} is now an org admin.");
    }

    public function destroy(Profile $profile): RedirectResponse
    {
        if ($profile->role !== 'org_admin') {
            return redirect()->route('org-admins.index')->with('error', 'That account is not an org admin.');
        }

        // Demote back to a plain passenger (org-less). Their account/login is kept.
        $profile->update(['role' => 'passenger', 'organization_id' => null]);

        return redirect()->route('org-admins.index')
            ->with('status', ($profile->full_name ?: 'The account').' was removed as an org admin (now a passenger).');
    }
}
