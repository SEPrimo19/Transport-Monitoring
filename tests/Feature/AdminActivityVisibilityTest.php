<?php

namespace Tests\Feature;

use App\Models\AccountAdminEvent;
use App\Models\Concerns\BelongsToOrganization;
use App\Support\AdminContext;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * account_admin_events has an access rule unlike every other tenant table, so it gets
 * its own pins. Migration 037:
 *
 *   org_admin    → organization_id = their own cooperative
 *   super_admin  → organization_id IS NULL (platform-tier acts ONLY)
 *
 * The console connects as the database owner and therefore BYPASSES that RLS policy,
 * so scopeVisibleTo is the real enforcement and these tests are what keep it honest.
 *
 * DB-free, like the rest of this suite: the SQL is inspected rather than executed,
 * because .env points at the live Supabase the mobile app writes to.
 */
class AdminActivityVisibilityTest extends TestCase
{
    private function contextFor(string $role, ?string $orgId): AdminContext
    {
        $ctx = new AdminContext;
        $ctx->role = $role;
        $ctx->organizationId = $orgId;

        return $ctx;
    }

    public function test_a_super_admin_sees_only_platform_tier_events(): void
    {
        $sql = AccountAdminEvent::query()
            ->visibleTo($this->contextFor('super_admin', null))
            ->toSql();

        $this->assertStringContainsString('"organization_id" is null', $sql,
            'A super_admin must be restricted to events with no organization. Appointing '.
            'administrators is theirs; reading what happens inside a cooperative is not.');
        $this->assertStringNotContainsString('1 = 0', $sql);
    }

    public function test_an_org_admin_sees_only_their_own_cooperative(): void
    {
        $query = AccountAdminEvent::query()
            ->visibleTo($this->contextFor('org_admin', 'org-uuid-1'));

        $this->assertStringContainsString('"organization_id" = ?', $query->toSql());
        $this->assertSame(['org-uuid-1'], $query->getBindings(),
            'The org filter must bind the signed-in admin\'s own organization.');
    }

    public function test_it_fails_closed_without_an_organization(): void
    {
        $sql = AccountAdminEvent::query()
            ->visibleTo($this->contextFor('org_admin', null))
            ->toSql();

        $this->assertStringContainsString('1 = 0', $sql,
            'No organization context must return nothing, never everything — the same '.
            'fail-closed rule OrganizationScope follows.');
    }

    public function test_the_model_does_not_carry_the_usual_organization_scope(): void
    {
        // Deliberate, and the opposite of the rule everywhere else. OrganizationScope
        // lets a super_admin read EVERY row, which is precisely what migration 037
        // forbids for this table. If someone "fixes" the inconsistency by adding the
        // trait, this fails and explains why.
        $this->assertNotContains(
            BelongsToOrganization::class,
            class_uses_recursive(AccountAdminEvent::class),
            'AccountAdminEvent must NOT use BelongsToOrganization: that scope grants a '.
            'super_admin every row, while 037 restricts them to organization_id IS NULL. '.
            'Use scopeVisibleTo instead.'
        );
    }

    public function test_the_screen_is_read_only(): void
    {
        $routes = collect(Route::getRoutes())->filter(
            fn ($r) => str_starts_with((string) $r->getName(), 'activity.')
        );

        $this->assertCount(1, $routes, 'Admin activity should expose exactly one route.');
        $this->assertSame(['GET', 'HEAD'], array_values($routes->first()->methods()),
            'The audit log is append-only; the console must offer no way to write it.');

        $source = file_get_contents(app_path('Http/Controllers/AdminActivityController.php'));
        foreach (['->insert(', '->update(', '->delete(', '->save(', 'DB::statement('] as $write) {
            $this->assertStringNotContainsString($write, $source,
                'AdminActivityController must never write to the audit table.');
        }
    }

    public function test_both_tiers_can_reach_it_and_it_is_still_behind_the_admin_gate(): void
    {
        $route = Route::getRoutes()->getByName('activity.index');
        $middleware = $route->middleware();

        $this->assertContains('supabase.admin', $middleware,
            'Admin activity must still require an authenticated admin.');

        // Neither tier gate: both roles open it and the SCOPE differs, not the access.
        $this->assertEmpty(array_intersect(['tenant.admin', 'super.admin'], $middleware),
            'activity.index is intentionally tier-free — scopeVisibleTo draws the line '.
            'instead, so both an org_admin and a super_admin can open it and see '.
            'different rows. It is listed in RouteTenantGateCoverageTest::TIER_FREE.');
    }
}
