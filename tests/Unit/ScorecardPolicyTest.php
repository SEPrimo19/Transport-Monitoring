<?php

namespace Tests\Unit;

use App\Support\ScorecardPolicy;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * These rules decide what is, and is not, held against a named driver. A complaint count
 * with no context is the easiest number in the system to misread, so every rule that stops
 * it being misread is pinned here: only driver conduct counts, "resolved" is not "upheld",
 * and a rate is withheld until there are enough trips for it to mean anything.
 */
class ScorecardPolicyTest extends TestCase
{
    private CarbonImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->now = CarbonImmutable::parse('2026-10-24 15:30:00', 'Asia/Manila');
    }

    // ── what counts against a driver ────────────────────────────────────────

    #[DataProvider('categories')]
    public function test_only_driver_conduct_counts_against_the_driver(string $category, string $bucket): void
    {
        $this->assertSame($bucket, ScorecardPolicy::complaintBucket($category));
    }

    public static function categories(): array
    {
        return [
            'driver behaviour is the driver\'s' => ['driver_behavior', 'driver'],
            'safety could be driving or the vehicle' => ['safety', 'safety'],
            'a failing vehicle is not the driver\'s' => ['vehicle_condition', 'not_driver'],
            'a route problem is not the driver\'s' => ['route_issue', 'not_driver'],
            'other is not attributed' => ['other', 'not_driver'],
            'an unknown category is not attributed' => ['something_new', 'not_driver'],
        ];
    }

    #[DataProvider('statuses')]
    public function test_a_status_is_bucketed_without_treating_resolved_as_upheld(string $status, ?string $outcome, string $bucket): void
    {
        $this->assertSame($bucket, ScorecardPolicy::statusBucket($status, $outcome));
    }

    public static function statuses(): array
    {
        return [
            'submitted is just received' => ['submitted', null, 'received'],
            'reviewing' => ['reviewing', null, 'under_review'],
            'resolved and upheld' => ['resolved', 'upheld', 'upheld'],
            'resolved and not upheld' => ['resolved', 'not_upheld', 'not_upheld'],
            'resolved with no outcome is NOT assumed upheld' => ['resolved', null, 'outcome_not_recorded'],
            'dismissed' => ['dismissed', null, 'dismissed'],
            'an outcome on a dismissed report is ignored' => ['dismissed', 'upheld', 'dismissed'],
        ];
    }

    // ── rates and percentages ───────────────────────────────────────────────

    public function test_a_rate_is_withheld_below_thirty_trips(): void
    {
        $this->assertNull(ScorecardPolicy::ratePer100(1, 29),
            'One complaint over 29 trips would read as 3.4 per 100, which says almost nothing.');
        $this->assertNull(ScorecardPolicy::ratePer100(0, 0));
        $this->assertSame(10.0, ScorecardPolicy::ratePer100(3, 30));
        $this->assertSame(0.0, ScorecardPolicy::ratePer100(0, 30));
    }

    public function test_on_time_is_null_not_zero_when_nothing_was_scored(): void
    {
        // A driver whose routes have no timetable has NOT been late 100% of the time.
        $this->assertNull(ScorecardPolicy::onTimePercent(0, 0));
        $this->assertSame(75.0, ScorecardPolicy::onTimePercent(3, 4));
        $this->assertSame(0.0, ScorecardPolicy::onTimePercent(0, 4));
        $this->assertSame(66.7, ScorecardPolicy::onTimePercent(2, 3));
    }

    // ── the reporting period ────────────────────────────────────────────────

    public function test_the_default_period_is_the_last_thirty_days_including_today(): void
    {
        $p = ScorecardPolicy::resolvePeriod(null, null, null, $this->now);

        $this->assertEquals($this->now->endOfDay(), $p['to']);
        $this->assertEquals($this->now->startOfDay()->subDays(29), $p['from']);
    }

    #[DataProvider('presets')]
    public function test_a_known_preset_sets_the_length(string $preset, int $days): void
    {
        $p = ScorecardPolicy::resolvePeriod($preset, null, null, $this->now);

        $this->assertEquals($this->now->startOfDay()->subDays($days - 1), $p['from']);
    }

    public static function presets(): array
    {
        return ['a week' => ['7', 7], 'thirty days' => ['30', 30], 'ninety days' => ['90', 90]];
    }

    public function test_an_unknown_preset_falls_back_to_thirty_days(): void
    {
        $p = ScorecardPolicy::resolvePeriod('365', null, null, $this->now);

        $this->assertEquals($this->now->startOfDay()->subDays(29), $p['from']);
    }

    public function test_a_custom_range_is_used_with_the_whole_end_day_included(): void
    {
        $p = ScorecardPolicy::resolvePeriod(null, '2026-09-01', '2026-09-15', $this->now);

        $this->assertSame('2026-09-01 00:00:00', $p['from']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-15 23:59:59', $p['to']->format('Y-m-d H:i:s'));
    }

    #[DataProvider('unusableRanges')]
    public function test_an_unusable_range_falls_back_instead_of_erroring(?string $from, ?string $to): void
    {
        $p = ScorecardPolicy::resolvePeriod(null, $from, $to, $this->now);

        $this->assertEquals($this->now->startOfDay()->subDays(29), $p['from']);
        $this->assertEquals($this->now->endOfDay(), $p['to']);
    }

    public static function unusableRanges(): array
    {
        return [
            'start after end' => ['2026-09-15', '2026-09-01'],
            'not a date' => ['yesterday', '2026-09-15'],
            'an impossible date' => ['2026-02-31', '2026-03-05'],
            'only a start' => ['2026-09-01', null],
            'only an end' => [null, '2026-09-15'],
            'blank strings' => ['', ''],
        ];
    }

    public function test_a_range_longer_than_366_days_keeps_the_end_and_moves_the_start(): void
    {
        $p = ScorecardPolicy::resolvePeriod(null, '2020-01-01', '2026-10-01', $this->now);

        $this->assertSame('2026-10-01 23:59:59', $p['to']->format('Y-m-d H:i:s'));
        $this->assertEquals($p['to']->subDays(366)->startOfDay(), $p['from']);
    }
}
