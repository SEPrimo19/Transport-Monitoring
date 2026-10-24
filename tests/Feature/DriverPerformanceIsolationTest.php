<?php

namespace Tests\Feature;

use App\Http\Controllers\DriverPerformanceController;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The driver scorecard puts performance figures and complaint counts beside named people, so
 * the three layers that keep one cooperative's drivers out of another's console are pinned
 * here, in the same shape as SearchTenantIsolationTest. DB-free; the behavioural proof, with
 * real rows from two cooperatives, is DriverPerformancePageTest.
 */
class DriverPerformanceIsolationTest extends TestCase
{
    private function source(): string
    {
        $path = (new \ReflectionClass(DriverPerformanceController::class))->getFileName();

        // Comments stripped: a docblock that states a rule in prose must not trip the rule.
        $code = '';
        foreach (token_get_all(file_get_contents($path)) as $token) {
            if (is_array($token)) {
                if ($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT) {
                    continue;
                }
                $code .= $token[1];
            } else {
                $code .= $token;
            }
        }

        return $code;
    }

    public function test_the_page_is_behind_the_tenant_gate(): void
    {
        $middleware = Route::getRoutes()->getByName('analytics.drivers.index')->middleware();

        $this->assertContains('supabase.admin', $middleware);
        $this->assertContains('tenant.admin', $middleware,
            'A super_admin appoints administrators; they do not read a cooperative\'s drivers.');
    }

    public function test_the_controller_has_no_way_around_the_organization_scope(): void
    {
        $source = $this->source();

        foreach ([
            'DB::table(', 'DB::select(', 'DB::raw(', 'DB::statement(', 'DB::connection(',
            'whereRaw(', 'withoutGlobalScope', '->toBase(', 'setConnection(',
        ] as $escape) {
            $this->assertStringNotContainsString($escape, $source,
                "$escape steps outside Eloquent and therefore outside OrganizationScope.");
        }
    }

    public function test_profiles_are_never_queried_directly(): void
    {
        // Profile carries no organization scope by design (passengers are org-less), so a direct
        // read would be bounded to nothing. Names arrive through Driver::profile(), whose parent
        // IS scoped.
        $this->assertStringNotContainsString('Profile::', $this->source());
    }

    public function test_the_controller_cannot_write(): void
    {
        $source = $this->source();

        foreach (['->insert(', '->update(', '->delete(', '->save(', '::create(', '->create('] as $write) {
            $this->assertStringNotContainsString($write, $source, "The scorecard is read-only ($write).");
        }
    }
}
