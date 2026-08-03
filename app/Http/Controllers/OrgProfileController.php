<?php

namespace App\Http\Controllers;

use App\Http\Requests\OrgProfileRequest;
use App\Models\Organization;
use App\Support\AdminContext;
use Illuminate\Http\RedirectResponse;
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
        if ($ctx->organizationId === null) {
            return redirect()->route('organizations.index')
                ->with('status', 'Pick a cooperative to edit from the Organizations list.');
        }

        return view('organization.edit', [
            'organization' => Organization::findOrFail($ctx->organizationId),
        ]);
    }

    public function update(OrgProfileRequest $request, AdminContext $ctx): RedirectResponse
    {
        if ($ctx->organizationId === null) {
            return redirect()->route('organizations.index');
        }

        // Load their OWN org by context id (not from the request) and update only
        // the self-service fields OrgProfileRequest allows — slug and status are
        // never in validated(), so they can't be changed here.
        $organization = Organization::findOrFail($ctx->organizationId);
        $organization->update($request->validated());

        return redirect()->route('organization.edit')
            ->with('status', 'Your organization profile was saved.');
    }
}
