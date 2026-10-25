<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * How an assignment reads on a driver's profile.
 *
 * An assignment has an explicit on/off switch (is_active) AND a validity window, and they can
 * disagree: a window that has closed while the switch is still on. An admin reads that as
 * "ended", and a switch that was turned off is "disabled" whatever the window says, because the
 * deliberate action is the more informative fact. A null bound is open on that side. Bounds are
 * inclusive, so an assignment is active at the very second it starts and the very second it ends.
 *
 * Pure: no model, no clock (the caller passes `now`), so every rule is tested without a database.
 */
final class AssignmentStatus
{
    public const UPCOMING = 'Upcoming';

    public const ACTIVE = 'Active now';

    public const ENDED = 'Ended';

    public const DISABLED = 'Disabled';

    public static function for(bool $isActive, ?CarbonInterface $startsAt, ?CarbonInterface $endsAt, CarbonInterface $now): string
    {
        if (! $isActive) {
            return self::DISABLED;
        }

        if ($endsAt !== null && $now->greaterThan($endsAt)) {
            return self::ENDED;
        }

        if ($startsAt !== null && $now->lessThan($startsAt)) {
            return self::UPCOMING;
        }

        return self::ACTIVE;
    }
}
