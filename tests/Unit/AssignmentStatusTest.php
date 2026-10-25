<?php

namespace Tests\Unit;

use App\Support\AssignmentStatus;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

/**
 * How an assignment reads on a driver's profile. An assignment has an explicit on/off switch and
 * a validity window, and they can disagree: the window can have closed while the switch is
 * still on. The profile must say what an admin would say out loud.
 */
class AssignmentStatusTest extends TestCase
{
    private function at(string $time): CarbonImmutable
    {
        return CarbonImmutable::parse("2026-10-26 $time");
    }

    public function test_a_disabled_assignment_is_disabled_even_inside_its_window(): void
    {
        $this->assertSame(AssignmentStatus::DISABLED, AssignmentStatus::for(false, $this->at('08:00'), $this->at('18:00'), $this->at('12:00')));
    }

    public function test_an_expired_assignment_still_flagged_active_is_ended(): void
    {
        $this->assertSame(AssignmentStatus::ENDED, AssignmentStatus::for(true, $this->at('06:00'), $this->at('10:00'), $this->at('12:00')));
    }

    public function test_a_future_assignment_is_upcoming(): void
    {
        $this->assertSame(AssignmentStatus::UPCOMING, AssignmentStatus::for(true, $this->at('14:00'), $this->at('20:00'), $this->at('12:00')));
    }

    public function test_a_current_assignment_is_active_now(): void
    {
        $this->assertSame(AssignmentStatus::ACTIVE, AssignmentStatus::for(true, $this->at('08:00'), $this->at('18:00'), $this->at('12:00')));
    }

    public function test_a_null_end_is_open_ended_and_a_null_start_is_already_started(): void
    {
        $this->assertSame(AssignmentStatus::ACTIVE, AssignmentStatus::for(true, $this->at('08:00'), null, $this->at('12:00')));
        $this->assertSame(AssignmentStatus::ACTIVE, AssignmentStatus::for(true, null, $this->at('18:00'), $this->at('12:00')));
        $this->assertSame(AssignmentStatus::UPCOMING, AssignmentStatus::for(true, $this->at('14:00'), null, $this->at('12:00')));
        $this->assertSame(AssignmentStatus::ENDED, AssignmentStatus::for(true, null, $this->at('10:00'), $this->at('12:00')));
    }

    public function test_both_bounds_null_is_active_now(): void
    {
        $this->assertSame(AssignmentStatus::ACTIVE, AssignmentStatus::for(true, null, null, $this->at('12:00')));
    }

    public function test_a_disabled_assignment_that_also_ended_is_disabled(): void
    {
        $this->assertSame(AssignmentStatus::DISABLED, AssignmentStatus::for(false, $this->at('06:00'), $this->at('10:00'), $this->at('12:00')));
    }

    public function test_the_boundaries_are_inclusive(): void
    {
        $this->assertSame(AssignmentStatus::ACTIVE, AssignmentStatus::for(true, $this->at('12:00'), $this->at('18:00'), $this->at('12:00')));
        $this->assertSame(AssignmentStatus::ACTIVE, AssignmentStatus::for(true, $this->at('08:00'), $this->at('12:00'), $this->at('12:00')));
    }
}
