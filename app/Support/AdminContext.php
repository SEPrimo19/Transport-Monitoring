<?php

namespace App\Support;

/**
 * Per-request identity of the signed-in admin, populated by
 * {@see \App\Http\Middleware\AuthenticateSupabaseAdmin}. The global
 * {@see \App\Models\Scopes\OrganizationScope} and the
 * {@see \App\Models\Concerns\BelongsToOrganization} trait read this to enforce
 * tenant isolation IN CODE (data-access option A bypasses Postgres RLS), mirroring
 * migration 016's `is_super_admin() OR organization_id = current_org_id()`.
 *
 * Bound as a `scoped` singleton (one instance per request) in AppServiceProvider.
 */
class AdminContext
{
    public ?string $userId = null;
    public ?string $role = null;
    public ?string $organizationId = null;
    public ?string $email = null;
    public ?string $fullName = null;

    public function isAuthenticated(): bool
    {
        return $this->userId !== null;
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isOrgAdmin(): bool
    {
        return $this->role === 'org_admin';
    }

    /** The admin tier permitted into the web console. */
    public function isAdmin(): bool
    {
        return in_array($this->role, ['org_admin', 'super_admin'], true);
    }

    public function fill(array $data): void
    {
        $this->userId = $data['userId'] ?? null;
        $this->role = $data['role'] ?? null;
        $this->organizationId = $data['organizationId'] ?? null;
        $this->email = $data['email'] ?? null;
        $this->fullName = $data['fullName'] ?? null;
    }

    public function toArray(): array
    {
        return [
            'userId' => $this->userId,
            'role' => $this->role,
            'organizationId' => $this->organizationId,
            'email' => $this->email,
            'fullName' => $this->fullName,
        ];
    }
}
