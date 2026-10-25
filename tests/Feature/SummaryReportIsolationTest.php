<?php

namespace Tests\Feature;

use App\Http\Controllers\SummaryReportController;
use App\Support\SummaryReportBuilder;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The dashboard summary composes the other reports and runs no query of its own, so the layers
 * that keep it that way, and keep people out of it, are pinned here in the shape of
 * FleetReportIsolationTest. DB-free; the behavioural proof is SummaryReportPageTest.
 */
class SummaryReportIsolationTest extends TestCase
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

    public function test_the_summary_runs_no_query_of_its_own(): void
    {
        foreach ([SummaryReportController::class, SummaryReportBuilder::class] as $class) {
            $source = $this->source($class);

            foreach ([
                '::query(', 'DB::', 'whereRaw(', 'withoutGlobalScope', '->toBase(', 'setConnection(', '->get(', '->first(',
            ] as $escape) {
                $this->assertStringNotContainsString($escape, $source,
                    "$class: $escape means the summary reads data itself instead of composing the individual reports.");
            }
        }
    }

    public function test_the_summary_never_reads_a_person(): void
    {
        foreach ([SummaryReportController::class, SummaryReportBuilder::class] as $class) {
            $source = $this->source($class);

            foreach (['full_name', 'passenger', 'description', 'reporter', 'employee_code'] as $person) {
                $this->assertStringNotContainsString($person, $source,
                    "$class mentions $person: a board pack names no individual.");
            }
        }
    }

    public function test_the_summary_routes_are_behind_the_tenant_gate(): void
    {
        foreach (['report-center.summary', 'report-center.summary.export'] as $name) {
            $middleware = Route::getRoutes()->getByName($name)->middleware();

            $this->assertContains('supabase.admin', $middleware, $name);
            $this->assertContains('tenant.admin', $middleware, "$name: a super_admin does not read a cooperative's operations.");
        }
    }
}
