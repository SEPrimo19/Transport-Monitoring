<?php

namespace Tests\Feature;

use App\Http\Controllers\TripReportController;
use App\Support\TripReportBuilder;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The trips report puts a cooperative's operating figures in a document that leaves the
 * console, so the layers that keep another cooperative's trips out of it are pinned here, in
 * the shape of DriverPerformanceIsolationTest. DB-free; the behavioural proof, with real rows
 * from two cooperatives, is TripReportPageTest.
 */
class TripReportIsolationTest extends TestCase
{
    private function source(string $class): string
    {
        $path = (new \ReflectionClass($class))->getFileName();

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

    public function test_the_trip_report_classes_have_no_way_around_the_organization_scope(): void
    {
        foreach ([TripReportController::class, TripReportBuilder::class] as $class) {
            $source = $this->source($class);

            foreach ([
                'DB::table(', 'DB::select(', 'DB::raw(', 'DB::statement(', 'DB::connection(',
                'whereRaw(', 'withoutGlobalScope', '->toBase(', 'setConnection(',
            ] as $escape) {
                $this->assertStringNotContainsString($escape, $source,
                    "$class: $escape steps outside Eloquent and therefore outside OrganizationScope.");
            }
        }
    }

    public function test_the_trip_report_routes_are_behind_the_tenant_gate(): void
    {
        foreach (['report-center.trips', 'report-center.trips.export'] as $name) {
            $middleware = Route::getRoutes()->getByName($name)->middleware();

            $this->assertContains('supabase.admin', $middleware, $name);
            $this->assertContains('tenant.admin', $middleware, "$name: a super_admin does not read a cooperative's operations.");
        }
    }
}
