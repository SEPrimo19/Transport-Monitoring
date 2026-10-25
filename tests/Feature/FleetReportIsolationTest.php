<?php

namespace Tests\Feature;

use App\Http\Controllers\FleetReportController;
use App\Support\FleetReportBuilder;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The vehicles and routes report leaves the console as a document, so the layers that keep
 * another cooperative's fleet out of it, and every person out of its queries, are pinned here in
 * the shape of ComplaintReportIsolationTest. DB-free; the behavioural proof is
 * FleetReportPageTest.
 */
class FleetReportIsolationTest extends TestCase
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

    public function test_the_fleet_report_classes_have_no_way_around_the_organization_scope(): void
    {
        foreach ([FleetReportController::class, FleetReportBuilder::class] as $class) {
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

    public function test_the_fleet_report_never_reads_a_person(): void
    {
        foreach ([FleetReportController::class, FleetReportBuilder::class] as $class) {
            $source = $this->source($class);

            foreach (['driver', 'passenger', 'full_name', 'description', 'reporter'] as $person) {
                $this->assertStringNotContainsString($person, $source,
                    "$class mentions $person: the vehicles and routes report names vehicles and routes, never people.");
            }
        }
    }

    public function test_the_fleet_report_routes_are_behind_the_tenant_gate(): void
    {
        foreach (['report-center.fleet', 'report-center.fleet.export'] as $name) {
            $middleware = Route::getRoutes()->getByName($name)->middleware();

            $this->assertContains('supabase.admin', $middleware, $name);
            $this->assertContains('tenant.admin', $middleware, "$name: a super_admin does not read a cooperative's fleet.");
        }
    }
}
