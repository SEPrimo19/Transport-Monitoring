<?php

namespace Tests\Unit;

use App\Support\ReportRequest;
use PHPUnit\Framework\TestCase;

/**
 * A range the person typed that cannot be used is replaced by the default period. That must
 * never be silent: the date inputs would then show dates they did not choose, and a report
 * handed to a board would cover a different period from the one they asked for.
 */
class ReportRequestTest extends TestCase
{
    public function test_no_notice_when_nothing_was_asked_for(): void
    {
        $this->assertNull(ReportRequest::noticeFor(null, null, null, true));
    }

    public function test_a_range_with_from_after_to_gets_a_notice(): void
    {
        $notice = ReportRequest::noticeFor('2026-10-20', '2026-10-01', null, true);

        $this->assertIsString($notice);
        $this->assertStringContainsString('last 30 days', $notice);
    }

    public function test_a_missing_end_date_gets_a_notice(): void
    {
        $this->assertIsString(ReportRequest::noticeFor('2026-10-01', null, null, true));
    }

    public function test_garbage_dates_get_a_notice(): void
    {
        $this->assertIsString(ReportRequest::noticeFor('soon', 'later', null, true));
        $this->assertIsString(ReportRequest::noticeFor('2026-02-31', '2026-03-05', null, true));
    }

    public function test_a_valid_range_has_no_notice(): void
    {
        $this->assertNull(ReportRequest::noticeFor('2026-10-01', '2026-10-20', null, true));
        $this->assertNull(ReportRequest::noticeFor('2026-10-05', '2026-10-05', null, true));
    }

    public function test_an_unusable_route_gets_a_notice(): void
    {
        $notice = ReportRequest::noticeFor(null, null, 'zzz', false);

        $this->assertIsString($notice);
        $this->assertStringContainsString('route', $notice);
    }

    public function test_a_usable_route_has_no_notice(): void
    {
        $this->assertNull(ReportRequest::noticeFor(null, null, 'f3c1c3f2-0000-4000-8000-000000000001', true));
    }
}
