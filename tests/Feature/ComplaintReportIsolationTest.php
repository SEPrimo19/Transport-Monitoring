<?php

namespace Tests\Feature;

use App\Http\Controllers\ComplaintReportController;
use App\Support\ComplaintReportBuilder;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The complaints report leaves the console as a document, so the layers that keep another
 * cooperative's reports out of it, and every identifying column out of the query, are pinned
 * here in the shape of TripReportIsolationTest. DB-free; the behavioural proof is
 * ComplaintReportPageTest.
 */
class ComplaintReportIsolationTest extends TestCase
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

    public function test_the_complaint_report_classes_have_no_way_around_the_organization_scope(): void
    {
        foreach ([ComplaintReportController::class, ComplaintReportBuilder::class] as $class) {
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

    public function test_the_complaint_report_never_selects_an_identifying_column(): void
    {
        foreach ([ComplaintReportController::class, ComplaintReportBuilder::class] as $class) {
            $source = $this->source($class);

            foreach (['passenger_profile_id', 'description', 'reporter', 'vehicle_id', 'full_name', 'plate_number'] as $identifying) {
                $this->assertStringNotContainsString($identifying, $source,
                    "$class mentions $identifying: the complaints report must not read who complained, what they wrote, or which vehicle.");
            }
        }
    }

    public function test_the_complaint_report_routes_are_behind_the_tenant_gate(): void
    {
        foreach (['report-center.complaints', 'report-center.complaints.export'] as $name) {
            $middleware = Route::getRoutes()->getByName($name)->middleware();

            $this->assertContains('supabase.admin', $middleware, $name);
            $this->assertContains('tenant.admin', $middleware, "$name: a super_admin does not read a cooperative's complaints.");
        }
    }
}
