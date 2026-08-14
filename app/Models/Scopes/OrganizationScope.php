<?php

namespace App\Models\Scopes;

use App\Models\Concerns\BelongsToOrganization;
use App\Support\AdminContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Global tenant isolation for data-access option A (direct Postgres — RLS is
 * bypassed on this connection, so Laravel MUST self-enforce the boundary).
 *
 *   super_admin  → no filter (cross-org), mirroring is_super_admin() in migration 016.
 *   org_admin    → organization_id = their org.
 *   no context   → FAIL CLOSED (return nothing), never everything.
 *
 * Apply via the {@see BelongsToOrganization} trait on every
 * tenant-scoped model. Do NOT apply to the Organization model (it is the tenant root).
 */
class OrganizationScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $ctx = app(AdminContext::class);

        if ($ctx->isSuperAdmin()) {
            return; // cross-org
        }

        if ($ctx->organizationId !== null) {
            $builder->where($model->getTable().'.organization_id', $ctx->organizationId);

            return;
        }

        // No org context (unauthenticated / non-admin) — never leak. The auth
        // middleware rejects these before a scoped model is queried; this is defence in depth.
        $builder->whereRaw('1 = 0');
    }
}
