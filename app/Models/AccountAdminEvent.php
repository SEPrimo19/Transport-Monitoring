<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Maps public.account_admin_events — the append-only record of who created, reset or
 * removed a driver or cooperative-administrator login.
 *
 * DELIBERATELY NOT {@see \App\Models\Concerns\BelongsToOrganization}. Every other
 * tenant table wants that trait, and this one would be actively wrong: its scope lets
 * a super_admin read everything, which is the opposite of what migration 037's policy
 * says. The access rule here is not "your org, or all orgs" but a genuine split:
 *
 *   org_admin    → organization_id = their own cooperative
 *   super_admin  → organization_id IS NULL (platform-tier acts ONLY)
 *
 * A super_admin appoints administrators; they do not get to read what happened inside
 * a cooperative afterwards. That mirrors the tenant.admin boundary the console draws
 * everywhere else, and it is why {@see scopeVisibleTo} exists instead of a global
 * scope — the rule is role-dependent in a way a global scope cannot express.
 *
 * The console connects as the database owner and therefore BYPASSES RLS, so the policy
 * in 037 does not protect this screen. scopeVisibleTo is the enforcement, and
 * AdminActivityVisibilityTest is what keeps it honest.
 *
 * Read-only by intent: there is no fillable array and nothing in the console writes
 * here except OrgAdminController's own audit insert, which uses the query builder.
 */
class AccountAdminEvent extends Model
{
    protected $table = 'account_admin_events';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $guarded = ['*'];

    protected $casts = [
        'succeeded' => 'boolean',
        'occurred_at' => 'datetime',
    ];

    /** Human labels for the `action` check constraint's four values. */
    public const ACTIONS = [
        'create' => 'Account created',
        'reset_password' => 'Password reset',
        'deactivate' => 'Access removed',
        'delete_account' => 'Account deleted',
    ];

    public const KINDS = [
        'driver' => 'Driver',
        'org_admin' => 'Org admin',
    ];

    /**
     * The only way this table should ever be read by the console.
     *
     * @param  Builder<AccountAdminEvent>  $query
     * @return Builder<AccountAdminEvent>
     */
    public function scopeVisibleTo(Builder $query, \App\Support\AdminContext $ctx): Builder
    {
        if ($ctx->isSuperAdmin()) {
            return $query->whereNull('organization_id');
        }

        // Fail closed, exactly like OrganizationScope: no org context means no rows,
        // never every row.
        if ($ctx->organizationId === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('organization_id', $ctx->organizationId);
    }

    public function actionLabel(): string
    {
        return self::ACTIONS[$this->action] ?? ucfirst(str_replace('_', ' ', (string) $this->action));
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->target_kind] ?? ucfirst((string) $this->target_kind);
    }

    /** Red for removals, amber for a password reset, neutral otherwise. */
    public function actionBadge(): string
    {
        return match ($this->action) {
            'create' => 'text-bg-success',
            'reset_password' => 'text-bg-warning',
            'deactivate', 'delete_account' => 'text-bg-danger',
            default => 'text-bg-secondary',
        };
    }
}
