<?php

namespace App\Http\Middleware;

use App\Support\AdminContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate for platform-tier (super_admin only) screens like Organizations. Runs
 * INSIDE the `supabase.admin` group, so AdminContext is already filled by
 * {@see AuthenticateSupabaseAdmin}. Fails closed: any role that isn't super_admin
 * (including an empty/unfilled context) gets a 403 — an org_admin must never be
 * able to manage tenants.
 */
class RequireSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! app(AdminContext::class)->isSuperAdmin()) {
            abort(403, 'This area is restricted to platform administrators.');
        }

        return $next($request);
    }
}
