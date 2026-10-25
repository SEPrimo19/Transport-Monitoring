<?php

namespace Tests\Feature;

use App\Http\Controllers\DriverProfileController;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The driver profile sits beside a named person's licence, phone and complaint history, so the
 * layers that keep another cooperative's drivers out of it are pinned here, in the shape of
 * DriverPerformanceIsolationTest. DB-free; the behavioural proof is DriverProfilePageTest.
 */
class DriverProfileIsolationTest extends TestCase
{
    private function source(): string
    {
        $path = (new \ReflectionClass(DriverProfileController::class))->getFileName();

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

    public function test_the_controller_has_no_way_around_the_organization_scope(): void
    {
        $source = $this->source();

        foreach ([
            'DB::', 'whereRaw(', 'withoutGlobalScope', '->toBase(', 'setConnection(',
        ] as $escape) {
            $this->assertStringNotContainsString($escape, $source,
                "$escape steps outside Eloquent and therefore outside OrganizationScope.");
        }
    }

    public function test_profiles_are_never_queried_directly(): void
    {
        $this->assertDoesNotMatchRegularExpression('/\bProfile::/', $this->source(),
            'Profile carries no organization scope (passengers are org-less): a name arrives through Driver::profile().');
    }

    public function test_the_driver_is_bound_through_the_scoped_model(): void
    {
        $type = (new \ReflectionMethod(DriverProfileController::class, 'show'))->getParameters()[0]->getType();

        $this->assertSame(\App\Models\Driver::class, $type?->getName(),
            'Dropping the Driver type-hint would turn the profile into a cross-tenant read.');
    }

    public function test_the_controller_never_reads_a_complainant(): void
    {
        $source = $this->source();

        foreach (['passenger_profile_id', 'description', 'reporter'] as $identifying) {
            $this->assertStringNotContainsString($identifying, $source,
                "The profile must not read who complained or what they wrote ($identifying).");
        }
    }

    public function test_the_route_is_behind_the_tenant_gate(): void
    {
        $middleware = Route::getRoutes()->getByName('drivers.show')->middleware();

        $this->assertContains('supabase.admin', $middleware);
        $this->assertContains('tenant.admin', $middleware,
            'A super_admin appoints administrators; they do not read a cooperative\'s drivers.');
    }
}
