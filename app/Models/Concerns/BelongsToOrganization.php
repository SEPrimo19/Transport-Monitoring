<?php

namespace App\Models\Concerns;

use App\Models\Scopes\OrganizationScope;
use App\Support\AdminContext;
use RuntimeException;

/**
 * Apply to every tenant-scoped Eloquent model (Vehicle, Route, Driver, Terminal,
 * Trip, Report, ...). Adds the global {@see OrganizationScope} for reads and
 * enforces organization_id on create.
 *
 * Write-path isolation (data-access option A bypasses RLS, so this is the only
 * guard):
 *   - super_admin — has no single org; MUST set organization_id explicitly
 *     (e.g. a form selector). We leave their value untouched.
 *   - org_admin / any scoped actor — organization_id is FORCED to their own org,
 *     overriding anything the client supplied. This closes a cross-tenant write
 *     escape: a scoped admin cannot plant a row in another org by POSTing a
 *     foreign organization_id, even if a controller forgets to strip it.
 *   - no org context — fail closed (refuse to create an unscoped row).
 */
trait BelongsToOrganization
{
    public static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope(new OrganizationScope);

        static::creating(function ($model): void {
            $ctx = app(AdminContext::class);

            if ($ctx->isSuperAdmin()) {
                return; // cross-org actor sets organization_id explicitly
            }

            if ($ctx->organizationId !== null) {
                // Force — do NOT trust a client-supplied organization_id.
                $model->organization_id = $ctx->organizationId;

                return;
            }

            throw new RuntimeException(
                'Refusing to create a tenant row without an organization context.'
            );
        });
    }
}
