<?php

namespace Tests\Unit;

use App\Support\ComplaintReportBuilder;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

/**
 * The figures and tables of the printed complaints report. Pure: arrays in, arrays out. The
 * cases that matter are the ones that would make a report unfair or untrue: a resolved report
 * with no recorded outcome called upheld, a time to close of 0 when nothing was closed, and a
 * report about a route that no longer exists.
 */
class ComplaintReportBuilderTest extends TestCase
{
    private function report(string $category = 'other', string $status = 'submitted', ?string $outcome = null, ?string $route = null, ?int $closedAfterMinutes = null): array
    {
        $created = CarbonImmutable::parse('2026-10-05 08:00:00');

        return [
            'category' => $category, 'status' => $status, 'outcome' => $outcome, 'route_id' => $route,
            'created_at' => $created,
            'resolved_at' => $closedAfterMinutes === null ? null : $created->addMinutes($closedAfterMinutes),
        ];
    }

    public function test_nothing_in_gives_zero_counts_and_null_times(): void
    {
        $r = ComplaintReportBuilder::build([], []);

        $this->assertSame(['received' => 0, 'closed' => 0, 'open' => 0, 'median_close_minutes' => null, 'slowest_close_minutes' => null], $r['figures']);
        $this->assertCount(5, $r['byCategory']);
        $this->assertCount(6, $r['byStatus']);
        $this->assertSame(array_fill(0, 5, 0), array_column($r['byCategory'], 'received'));
        $this->assertSame(array_fill(0, 6, 0), array_column($r['byStatus'], 'count'));
        $this->assertSame([], $r['byRoute']);
    }

    public function test_received_closed_and_open_are_counted(): void
    {
        $r = ComplaintReportBuilder::build([
            $this->report(status: 'submitted'),
            $this->report(status: 'reviewing'),
            $this->report(status: 'resolved', outcome: 'upheld', closedAfterMinutes: 60),
            $this->report(status: 'dismissed', closedAfterMinutes: 30),
        ], []);

        $this->assertSame(4, $r['figures']['received']);
        $this->assertSame(2, $r['figures']['closed']);
        $this->assertSame(2, $r['figures']['open']);
    }

    public function test_a_dismissed_report_counts_as_closed_with_its_time(): void
    {
        $r = ComplaintReportBuilder::build([$this->report(status: 'dismissed', closedAfterMinutes: 90)], []);

        $this->assertSame(1, $r['figures']['closed']);
        $this->assertSame(90, $r['figures']['median_close_minutes']);
    }

    public function test_median_and_slowest_time_to_close(): void
    {
        $three = ComplaintReportBuilder::build([
            $this->report(status: 'resolved', closedAfterMinutes: 60),
            $this->report(status: 'resolved', closedAfterMinutes: 120),
            $this->report(status: 'resolved', closedAfterMinutes: 600),
        ], []);
        $this->assertSame(120, $three['figures']['median_close_minutes']);
        $this->assertSame(600, $three['figures']['slowest_close_minutes']);

        $four = ComplaintReportBuilder::build([
            $this->report(status: 'resolved', closedAfterMinutes: 60),
            $this->report(status: 'resolved', closedAfterMinutes: 120),
            $this->report(status: 'resolved', closedAfterMinutes: 180),
            $this->report(status: 'resolved', closedAfterMinutes: 240),
        ], []);
        $this->assertSame(150, $four['figures']['median_close_minutes']);
    }

    public function test_times_are_null_not_zero_when_nothing_was_closed(): void
    {
        $r = ComplaintReportBuilder::build([$this->report(status: 'submitted'), $this->report(status: 'reviewing')], []);

        $this->assertNull($r['figures']['median_close_minutes']);
        $this->assertNull($r['figures']['slowest_close_minutes']);
    }

    public function test_a_report_closed_after_the_period_still_has_its_real_duration(): void
    {
        $r = ComplaintReportBuilder::build([
            [
                'category' => 'other', 'status' => 'resolved', 'outcome' => 'not_upheld', 'route_id' => null,
                'created_at' => CarbonImmutable::parse('2026-10-28 08:00:00'),
                'resolved_at' => CarbonImmutable::parse('2026-11-07 08:00:00'),
            ],
        ], []);

        $this->assertSame(14400, $r['figures']['median_close_minutes']);
    }

    public function test_a_negative_duration_is_clamped_to_zero(): void
    {
        $r = ComplaintReportBuilder::build([[
            'category' => 'other', 'status' => 'resolved', 'outcome' => null, 'route_id' => null,
            'created_at' => CarbonImmutable::parse('2026-10-05 08:00:00'),
            'resolved_at' => CarbonImmutable::parse('2026-10-05 07:00:00'),
        ]], []);

        $this->assertSame(0, $r['figures']['median_close_minutes']);
    }

    public function test_a_closed_report_with_no_resolved_at_counts_as_closed_but_has_no_time(): void
    {
        $r = ComplaintReportBuilder::build([$this->report(status: 'resolved')], []);

        $this->assertSame(1, $r['figures']['closed']);
        $this->assertNull($r['figures']['median_close_minutes']);
    }

    public function test_by_status_never_calls_a_resolved_report_upheld_without_an_outcome(): void
    {
        $r = ComplaintReportBuilder::build([
            $this->report(status: 'resolved', outcome: null, closedAfterMinutes: 10),
            $this->report(status: 'resolved', outcome: 'upheld', closedAfterMinutes: 10),
            $this->report(status: 'resolved', outcome: 'not_upheld', closedAfterMinutes: 10),
        ], []);

        $byBucket = array_column($r['byStatus'], 'count', 'bucket');

        $this->assertSame(1, $byBucket['upheld']);
        $this->assertSame(1, $byBucket['not_upheld']);
        $this->assertSame(1, $byBucket['outcome_not_recorded']);
        $this->assertSame(
            ['received', 'under_review', 'upheld', 'not_upheld', 'outcome_not_recorded', 'dismissed'],
            array_column($r['byStatus'], 'bucket')
        );
    }

    public function test_by_category_scope_labels_follow_the_fairness_rules(): void
    {
        $r = ComplaintReportBuilder::build([], []);
        $scope = array_column($r['byCategory'], 'scope', 'category');

        $this->assertSame('driver', $scope['driver_behavior']);
        $this->assertSame('safety', $scope['safety']);
        foreach (['vehicle_condition', 'route_issue', 'other'] as $notDriver) {
            $this->assertSame('not_driver', $scope[$notDriver]);
        }
    }

    public function test_an_unknown_category_is_counted_as_other(): void
    {
        $r = ComplaintReportBuilder::build([$this->report(category: 'something_new')], []);

        $this->assertSame(1, array_column($r['byCategory'], 'received', 'category')['other']);
    }

    public function test_by_route_merges_null_and_deleted_routes_into_one_last_row(): void
    {
        $r = ComplaintReportBuilder::build(
            [
                $this->report(route: null),
                $this->report(route: 'gone'),
                $this->report(route: 'r1'),
            ],
            ['r1' => ['code' => 'ALN-CAL', 'name' => 'Allen to Calbayog']]
        );

        $this->assertSame(['ALN-CAL', 'Not linked to a route'], array_column($r['byRoute'], 'route_code'));
        $this->assertSame([1, 2], array_column($r['byRoute'], 'received'));
    }

    public function test_by_route_is_sorted_by_code_not_by_volume(): void
    {
        $r = ComplaintReportBuilder::build(
            [$this->report(route: 'b'), $this->report(route: 'b'), $this->report(route: 'b'), $this->report(route: 'a')],
            ['a' => ['code' => 'ALN-CAL', 'name' => ''], 'b' => ['code' => 'CAL-ALN', 'name' => '']]
        );

        $this->assertSame(['ALN-CAL', 'CAL-ALN'], array_column($r['byRoute'], 'route_code'));
    }

    public function test_by_route_counts_closed_and_open_separately(): void
    {
        $r = ComplaintReportBuilder::build(
            [
                $this->report(status: 'resolved', route: 'r1', closedAfterMinutes: 5),
                $this->report(status: 'submitted', route: 'r1'),
                $this->report(status: 'reviewing', route: 'r1'),
            ],
            ['r1' => ['code' => 'A', 'name' => 'a']]
        );

        $this->assertSame(3, $r['byRoute'][0]['received']);
        $this->assertSame(1, $r['byRoute'][0]['closed']);
        $this->assertSame(2, $r['byRoute'][0]['open']);
    }
}
