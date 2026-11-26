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

    // ---- a passenger who deleted their account (migration 049) ----------------------------

    public function test_a_report_whose_passenger_deleted_their_account_is_labelled_so(): void
    {
        // The reporter id is null once the account is gone. That, not a missing name, is what
        // says "deleted": a live profile with an empty name must stay a plain null.
        $report = $this->report([
            'is_anonymous' => false,
            'passenger_profile_id' => null,
            'reporter_name' => null,
        ]);

        $this->assertSame('Former passenger (account deleted)', $report->reporterDisplayName());
    }

    public function test_anonymity_still_wins_over_the_deleted_label(): void
    {
        $report = $this->report([
            'is_anonymous' => true,
            'passenger_profile_id' => null,
            'reporter_name' => null,
        ]);

        $this->assertSame('Anonymous', $report->reporterDisplayName());
    }

    public function test_a_live_passenger_with_no_name_is_not_called_deleted(): void
    {
        $report = $this->report([
            'is_anonymous' => false,
            'passenger_profile_id' => '11111111-1111-1111-1111-111111111111',
            'reporter_name' => null,
        ]);

        $this->assertNull($report->reporterDisplayName());
    }

    public function test_a_row_that_did_not_load_the_reporter_column_is_not_mislabelled(): void
    {
        // A query that selects only some columns has no passenger_profile_id attribute at all.
        // "Not loaded" is not "deleted": without this guard every such row would be mislabelled.
        $report = $this->report([
            'is_anonymous' => false,
            'reporter_name' => null,
        ]);

        $this->assertNull($report->reporterDisplayName());
    }

    public function test_the_deletion_timestamp_alone_also_marks_a_deleted_reporter(): void
    {
        // Raw attributes, as a real query result carries them: setAttribute() on a datetime
        // cast needs a database connection, which a bare unit test does not have.
        $report = new Report;
        $report->setRawAttributes([
            'is_anonymous' => false,
            'reporter_deleted_at' => '2026-11-26 10:00:00',
            'reporter_name' => null,
        ]);
        $report->setRelation('reporter', null);

        $this->assertSame('Former passenger (account deleted)', $report->reporterDisplayName());
    }

    public function test_the_deleted_flag_is_exposed_for_the_views(): void
    {
        $deleted = $this->report(['passenger_profile_id' => null]);
        $live = $this->report(['passenger_profile_id' => '11111111-1111-1111-1111-111111111111']);

        $this->assertTrue($deleted->reporterAccountDeleted());
        $this->assertFalse($live->reporterAccountDeleted());
    }
}
