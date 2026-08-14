<?php

namespace Tests\Unit;

use App\Models\Profile;
use App\Models\Report;
use PHPUnit\Framework\TestCase;

/**
 * The anonymity promise made to passengers who report driver misconduct.
 *
 * `reporterDisplayName()` is the single place an admin-facing surface resolves a
 * reporter's identity, and it is what keeps that promise in the console. The reporter
 * FK is deliberately still stored — the passenger needs their own report history, and
 * abuse must remain traceable — so masking is a behaviour, not an absence of data. That
 * makes it exactly the kind of thing that breaks quietly when someone reads
 * `$report->reporter->full_name` directly instead.
 *
 * KNOWN LIMITATION, deliberately not asserted here: this masking is view-layer only.
 * The 2026-08-14 audit (finding M-3) established that an org_admin querying PostgREST
 * with their own token still receives `passenger_profile_id`, so anonymity is
 * pseudonymity against a determined admin. These tests pin the console behaviour; they
 * do not claim the data-layer guarantee the product copy implies.
 */
class ReportAnonymityTest extends TestCase
{
    private function report(array $attributes, ?object $reporter = null): Report
    {
        $report = new Report;
        // Bypasses $fillable — these are read paths, and the guard is not under test.
        foreach ($attributes as $key => $value) {
            $report->setAttribute($key, $value);
        }

        // Seed the relation explicitly. Without this, the `reporter_name ?? reporter`
        // fallback lazy-loads and needs a live database connection, which would turn a
        // pure logic test into an integration test — and this behaviour has nothing to
        // do with the database.
        $report->setRelation('reporter', $reporter);

        return $report;
    }

    public function test_an_anonymous_report_never_shows_the_reporter_name(): void
    {
        $report = $this->report([
            'is_anonymous' => true,
            'reporter_name' => 'Maria Santos',
        ]);

        $this->assertSame('Anonymous', $report->reporterDisplayName());
    }

    public function test_anonymity_wins_even_when_a_joined_name_is_present(): void
    {
        // The dashboard JOINs profiles rather than eager-loading, to save a round trip.
        // That query supplies `reporter_name`, and an earlier version of the dashboard
        // rendered it directly — naming a passenger who had asked not to be named. The
        // anonymity check must come first regardless of how the row was fetched.
        $report = $this->report([
            'is_anonymous' => true,
            'reporter_name' => 'Juan dela Cruz',
        ]);

        $this->assertNotSame('Juan dela Cruz', $report->reporterDisplayName());
    }

    public function test_a_named_report_shows_the_joined_name(): void
    {
        $report = $this->report([
            'is_anonymous' => false,
            'reporter_name' => 'Maria Santos',
        ]);

        $this->assertSame('Maria Santos', $report->reporterDisplayName());
    }

    public function test_a_named_report_with_no_name_available_returns_null(): void
    {
        // Distinct from "Anonymous": the passenger did not ask to be hidden, the name
        // simply is not on this row. Callers render their own placeholder rather than
        // implying an anonymity choice that was never made.
        $report = $this->report([
            'is_anonymous' => false,
            'reporter_name' => null,
        ]);

        $this->assertNull($report->reporterDisplayName());
    }

    public function test_a_named_report_falls_back_to_the_loaded_relation(): void
    {
        // The path every caller except the dashboard takes: no joined `reporter_name`,
        // so the eager-loaded profile supplies it.
        $profile = new Profile;
        $profile->setAttribute('full_name', 'Ana Reyes');

        $report = $this->report([
            'is_anonymous' => false,
            'reporter_name' => null,
        ], $profile);

        $this->assertSame('Ana Reyes', $report->reporterDisplayName());
    }
}
