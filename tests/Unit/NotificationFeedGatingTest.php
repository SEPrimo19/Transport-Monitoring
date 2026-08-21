<?php

namespace Tests\Unit;

use App\Providers\AppServiceProvider;
use App\Support\AdminContext;
use App\Support\PendingReportFeed;
use Illuminate\Support\Facades\View;
use RuntimeException;
use Tests\TestCase;

/**
 * The notification-bell view composer in {@see AppServiceProvider::boot()}
 * must gate {@see PendingReportFeed} on `isOrgAdmin()`, not `isAdmin()`.
 * `PendingReportFeed::fetch()` is a cross-org query the moment `OrganizationScope`
 * applies no filter — exactly the super_admin case — so `isAdmin()` (true for both
 * tiers) would run a needless cross-tenant reports query on every page a platform
 * admin loads, for a bell that the layout never even renders for that tier.
 *
 * `PendingReportFeed` is swapped for a fake in the container: the real one queries
 * `reports`/`vehicles`/`routes`, and this suite has no test database configured (see
 * phpunit.xml). The fake proves WHETHER the composer calls it without ever needing a
 * live connection. `organizationId` is left null on both contexts so the unrelated
 * org-chrome composer (which looks up `Organization::find()` only when it is set)
 * stays DB-free too.
 */
class NotificationFeedGatingTest extends TestCase
{
    public function test_super_admin_never_triggers_the_cross_org_reports_query(): void
    {
        $this->app->instance(PendingReportFeed::class, new class extends PendingReportFeed
        {
            public function payload(): array
            {
                throw new RuntimeException('PendingReportFeed::payload() must not run for a super_admin.');
            }
        });

        $ctx = $this->app->make(AdminContext::class);
        $ctx->fill(['userId' => 'user-1', 'role' => 'super_admin', 'organizationId' => null]);

        // Rendering at all — without the fake's exception firing — is the proof:
        // the composer never called payload() for this tier. (The bell DIV itself is
        // separately gated on isOrgAdmin() too, so it won't appear here either, but
        // that gate is not what this test is pinning. The double-quoted HTML
        // attribute is checked rather than the bare id string, because the polling
        // <script> further down the layout references `getElementById('notif-bell')`
        // — with single quotes — unconditionally, as a harmless no-op guard.)
        $html = View::make('layouts.app')->render();

        $this->assertStringNotContainsString('id="notif-bell"', $html);
    }

    public function test_org_admin_still_gets_the_pending_report_feed(): void
    {
        $this->app->instance(PendingReportFeed::class, new class extends PendingReportFeed
        {
            public function payload(): array
            {
                return ['count' => 3, 'items' => []];
            }
        });

        $ctx = $this->app->make(AdminContext::class);
        $ctx->fill(['userId' => 'user-2', 'role' => 'org_admin', 'organizationId' => null]);

        $html = View::make('layouts.app')->render();

        $this->assertStringContainsString('notif-bell', $html);
        // The fake's count, not the empty-fallback's — proves the real feed was reached.
        $this->assertMatchesRegularExpression('/id="notif-badge-count">\s*3\s*</', $html);
    }
}
