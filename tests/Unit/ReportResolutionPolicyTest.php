<?php

namespace Tests\Unit;

use App\Models\Report;
use App\Support\ReportResolutionPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory as ValidatorFactory;
use Tests\TestCase;

/**
 * "Resolved" tells a passenger the cooperative reviewed and closed their report. It does not
 * say the complaint was true, so closing one now requires saying whether it was upheld, and
 * reopening it has to clear that, or a report could sit "submitted" while still carrying a
 * verdict. The database refuses that contradiction (migration 046); these keep the console
 * from ever asking it to.
 *
 * Extends the framework TestCase rather than PHPUnit's: Report casts resolved_at to a datetime,
 * and assigning one makes Eloquent ask the database manager for its date format. That needs the
 * framework booted, not a connection; nothing here queries.
 */
class ReportResolutionPolicyTest extends TestCase
{
    private CarbonImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();
        // In the app timezone, as the controller's now() is. Eloquent stores a datetime attribute as a
        // naive string, so a fixture built in another zone would be re-read as if it were app time.
        $this->now = CarbonImmutable::parse('2026-10-24 07:30:00', config('app.timezone'));
    }

    private function validate(array $data): Validator
    {
        return (new ValidatorFactory(new Translator(new ArrayLoader, 'en')))
            ->make($data, ReportResolutionPolicy::rules());
    }

    // ── what the form must supply ───────────────────────────────────────────

    public function test_resolving_requires_an_outcome(): void
    {
        $this->assertTrue($this->validate(['status' => 'resolved'])->fails());
        $this->assertTrue($this->validate(['status' => 'resolved', 'resolution_outcome' => ''])->fails());
        $this->assertTrue($this->validate(['status' => 'resolved', 'resolution_outcome' => 'guilty'])->fails());
    }

    public function test_resolving_with_either_outcome_is_accepted(): void
    {
        $this->assertTrue($this->validate(['status' => 'resolved', 'resolution_outcome' => 'upheld'])->passes());
        $this->assertTrue($this->validate(['status' => 'resolved', 'resolution_outcome' => 'not_upheld'])->passes());
    }

    public function test_no_outcome_is_needed_for_any_other_status(): void
    {
        foreach (['submitted', 'reviewing', 'dismissed'] as $status) {
            $this->assertTrue($this->validate(['status' => $status])->passes(), $status);
        }
    }

    public function test_an_unknown_status_is_rejected(): void
    {
        $this->assertTrue($this->validate(['status' => 'resolved-ish'])->fails());
    }

    // ── what is written ─────────────────────────────────────────────────────

    public function test_resolving_records_the_outcome_who_and_when(): void
    {
        $report = new Report;

        ReportResolutionPolicy::apply($report, 'resolved', 'upheld', 'actor-1', $this->now);

        $this->assertSame('resolved', $report->status);
        $this->assertSame('upheld', $report->resolution_outcome);
        $this->assertSame('actor-1', $report->resolved_by_profile_id);
        $this->assertSame($this->now->getTimestamp(), $report->resolved_at->getTimestamp());
    }

    public function test_reopening_clears_the_outcome_as_well_as_who_and_when(): void
    {
        foreach (['submitted', 'reviewing'] as $reopened) {
            $report = new Report;
            ReportResolutionPolicy::apply($report, 'resolved', 'upheld', 'actor-1', $this->now);

            ReportResolutionPolicy::apply($report, $reopened, null, 'actor-2', $this->now);

            $this->assertSame($reopened, $report->status);
            $this->assertNull($report->resolution_outcome, "reopened to $reopened must not keep a verdict");
            $this->assertNull($report->resolved_at);
            $this->assertNull($report->resolved_by_profile_id);
        }
    }

    public function test_dismissing_closes_it_without_an_outcome_even_if_one_was_posted(): void
    {
        // The Upheld/Not upheld radios are hidden, not removed, when the status changes, so a
        // dismissed report can arrive carrying a stale "upheld". It must not be stored.
        $report = new Report;

        ReportResolutionPolicy::apply($report, 'dismissed', 'upheld', 'actor-1', $this->now);

        $this->assertSame('dismissed', $report->status);
        $this->assertNull($report->resolution_outcome);
        $this->assertSame('actor-1', $report->resolved_by_profile_id);
        $this->assertSame($this->now->getTimestamp(), $report->resolved_at->getTimestamp());
    }

    // ── what the admin reads back ───────────────────────────────────────────

    public function test_the_outcome_label_never_assumes_a_verdict(): void
    {
        $report = new Report;
        $report->status = 'resolved';

        $report->resolution_outcome = 'upheld';
        $this->assertSame('Upheld', $report->outcomeLabel());

        $report->resolution_outcome = 'not_upheld';
        $this->assertSame('Not upheld', $report->outcomeLabel());

        $report->resolution_outcome = null;
        $this->assertSame('Outcome not recorded', $report->outcomeLabel(),
            'A report resolved before outcomes existed must not read as upheld, or as not.');
    }

    public function test_there_is_no_outcome_label_until_a_report_is_resolved(): void
    {
        $report = new Report;
        $report->status = 'reviewing';

        $this->assertSame('', $report->outcomeLabel());
    }
}
