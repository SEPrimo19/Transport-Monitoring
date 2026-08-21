<?php

namespace App\Http\Middleware;

use App\Models\Scopes\OrganizationScope;
use App\Support\AdminContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate for TENANT data: vehicles, drivers, terminals, routes, schedules, assignments,
 * trips, reports, live operations. The mirror image of {@see RequireSuperAdmin}.
 *
 * Only an org_admin passes. A super_admin is refused, deliberately.
 *
 * WHY A PLATFORM ADMIN IS KEPT OUT OF TENANT DATA
 * A super_admin is the landlord. Their job is creating and suspending cooperatives and
 * appointing their administrators, not reading what happens inside one.
 *
 * The privacy case is concrete rather than theoretical. A passenger filing a misconduct
 * report names a driver and may attach photo or video evidence. They consented to their
 * own cooperative reviewing that, not to a platform operator at a different company
 * reading it. The same reasoning covers driver licence numbers and phone numbers, and
 * trip history, which is location data about identifiable people. Processing any of it
 * for platform administration is beyond the purpose it was collected for.
 *
 * Before this gate, {@see OrganizationScope} applied NO filter for a
 * super_admin, so every screen in this console showed every cooperative's rows. Hiding the
 * sidebar links alone would have been cosmetic: the routes still answered.
 *
 * WHEN A PLATFORM ADMIN GENUINELY NEEDS TO LOOK
 * There is no in-console path — a super_admin cannot self-promote. OrgAdminController
 * refuses to promote an account that is already a super_admin, OnboardingController
 * does the same, and migrations 020/022 put profile role/org changes behind
 * service_role. Support access into a cooperative is a database-level act, granted by
 * policy outside this console, not a console feature. That is deliberate: ambient
 * access that nobody can see is what this middleware removes, and inventing an in-app
 * "grant yourself access" button would just recreate it.
 */
class RequireTenantAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $ctx = app(AdminContext::class);

        if ($ctx->isSuperAdmin()) {
            abort(403,
                'Platform administrators manage cooperatives, not their data. '.
                'Support access to a specific cooperative is granted at the database level, by policy — '.
                'there is no self-service way to grant it from this console.');
        }

        // Fails closed. An org_admin without an organization has nothing to scope to, and
        // OrganizationScope would return nothing anyway; refusing here is clearer than
        // rendering an empty screen that looks like missing data.
        if (! $ctx->isOrgAdmin() || $ctx->organizationId === null) {
            abort(403,
                'Your administrator account is not linked to a cooperative. '.
                'Ask the platform administrator to assign one.');
        }

        return $next($request);
    }
}
