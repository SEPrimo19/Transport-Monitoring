<?php

namespace App\Http\Controllers;

use App\Http\Requests\OrgProfileRequest;
use App\Models\Organization;
use App\Providers\AppServiceProvider;
use App\Support\AdminContext;
use App\Support\OrgLogoUploader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * A cooperative's self-service profile — name, branding, contact, region and the
 * data-retention setting — edited by ITS OWN org_admin.
 *
 * The org being edited is ALWAYS AdminContext::$organizationId (the signed-in
 * admin's own tenant), never a request parameter, so an org_admin can only ever
 * touch their own row. The Organization model is the tenant root and is NOT
 * org-scoped, which is exactly why the boundary is enforced here by loading their
 * own id explicitly. Branding written here re-themes both the mobile app (which
 * reads primary_color) and this admin console (layouts.app resolves brandColor).
 *
 * A super_admin has no single org (organizationId is null) — they manage every
 * tenant through the Organizations screens, so we bounce them there.
 */
class OrgProfileController extends Controller
{
    public function edit(AdminContext $ctx): View|RedirectResponse
    {
        // Defence-in-depth, not reachable in practice: this route sits behind
        // tenant.admin, which refuses a super_admin outright and refuses an org_admin
        // with no organizationId before the request ever reaches here.
        if ($ctx->organizationId === null) {
            return redirect()->route('organizations.index')
                ->with('status', 'Pick a cooperative to edit from the Organizations list.');
        }

        return view('organization.edit', [
            'organization' => Organization::findOrFail($ctx->organizationId),
        ]);
    }

    public function update(OrgProfileRequest $request, AdminContext $ctx, OrgLogoUploader $logos): RedirectResponse
    {
        // Defence-in-depth, not reachable in practice — see edit() above.
        if ($ctx->organizationId === null) {
            return redirect()->route('organizations.index');
        }

        // Load their OWN org by context id (not from the request) and update only
        // the self-service fields OrgProfileRequest allows — slug and status are
        // never in validated(), so they can't be changed here.
        $organization = Organization::findOrFail($ctx->organizationId);
        $data = Arr::except($request->validated(), ['logo_file']);

        // A chosen file overrides the Logo URL text field. Upload first so a
        // failure aborts the save and keeps the admin's other edits intact.
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

        // Sidebar name/colour/logo are served from a cached copy of this row.
        Cache::forget(AppServiceProvider::orgChromeCacheKey((string) $organization->getKey()));

        return redirect()->route('organization.edit')
            ->with('status', 'Your organization profile was saved.');
    }
}
