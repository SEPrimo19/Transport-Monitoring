<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeZone;
use Throwable;

/**
 * The rules that stop a driver scorecard becoming a verdict.
 *
 * A complaint count with no context is the easiest number in the system to misread, and it is
 * shown beside a named person. Every rule here exists to keep it honest:
 *
 *  - only driver CONDUCT counts against the driver. A failing vehicle or a route problem is
 *    not theirs, and `safety` could be either, so it is shown on its own;
 *  - `resolved` is NOT `upheld`. The passenger-facing copy says a resolved report was
 *    "reviewed and closed", which includes complaints that turned out to be mistaken, so an
 *    outcome is required to call one upheld and a missing outcome is never assumed;
 *  - a rate per 100 trips is withheld until there are enough trips for it to mean anything
 *    (one complaint over two trips is not "50%");
 *  - a percentage over nothing is NULL, never 0: a driver whose routes have no timetable has
 *    not been late 100% of the time.
 *
 * There is deliberately no composite score and no ranking here, and none should be added.
 *
 * Pure: no model, no query, no clock (the caller passes `now`), so every rule is tested
 * without a database.
 */
final class ScorecardPolicy
{
    /** Below this many trips a rate per 100 trips is not shown. */
    public const MIN_TRIPS_FOR_RATE = 30;

    /** The longest period a report may cover. */
    public const MAX_RANGE_DAYS = 366;

    public const DEFAULT_DAYS = 30;

    /** Period presets offered in the console, in days. */
    public const PRESETS = [7, 30, 90];

    /**
     * The reporting window, always whole days in `now`'s timezone, `to` inclusive of its whole
     * day. Anything unusable falls back to the default rather than erroring: a mistyped URL is
     * not worth a 500.
     *
     * A valid custom range wins over a preset. A range over MAX_RANGE_DAYS keeps its end and
     * moves its start, because the end is the date the person is most likely asking about.
     *
     * @return array{from: CarbonImmutable, to: CarbonImmutable}
     */
    public static function resolvePeriod(?string $preset, ?string $from, ?string $to, CarbonImmutable $now): array
    {
        $tz = $now->getTimezone();
        $start = self::parseDay($from, $tz);
        $end = self::parseDay($to, $tz);

        if ($start !== null && $end !== null && $start <= $end) {
            $end = $end->endOfDay();
            $earliest = $end->subDays(self::MAX_RANGE_DAYS - 1)->startOfDay();

            return ['from' => $start < $earliest ? $earliest : $start->startOfDay(), 'to' => $end];
        }

        $days = ($preset !== null && ctype_digit($preset) && in_array((int) $preset, self::PRESETS, true))
            ? (int) $preset
            : self::DEFAULT_DAYS;

        return [
            'from' => $now->startOfDay()->subDays($days - 1),
            'to' => $now->endOfDay(),
        ];
    }

    /** @return 'driver'|'safety'|'not_driver' */
    public static function complaintBucket(string $category): string
    {
        return match ($category) {
            'driver_behavior' => 'driver',
            'safety' => 'safety',
            default => 'not_driver',
        };
    }

    /** @return 'received'|'under_review'|'upheld'|'not_upheld'|'outcome_not_recorded'|'dismissed' */
    public static function statusBucket(string $status, ?string $outcome): string
    {
        return match ($status) {
            'reviewing' => 'under_review',
            'dismissed' => 'dismissed',
            'resolved' => match ($outcome) {
                'upheld' => 'upheld',
                'not_upheld' => 'not_upheld',
                default => 'outcome_not_recorded',
            },
            default => 'received',
        };
    }

    public static function ratePer100(int $complaints, int $trips): ?float
    {
        if ($trips < self::MIN_TRIPS_FOR_RATE) {
            return null;
        }

        return round($complaints / $trips * 100, 1);
    }

    public static function onTimePercent(int $onTime, int $scored): ?float
    {
        if ($scored === 0) {
            return null;
        }

        return round($onTime / $scored * 100, 1);
    }

    /** A strict Y-m-d, or null. "2026-02-31" is rejected rather than rolled into March. */
    private static function parseDay(?string $value, DateTimeZone $tz): ?CarbonImmutable
    {
        if ($value === null || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            $day = CarbonImmutable::createFromFormat('!Y-m-d', $value, $tz);
        } catch (Throwable) {
            return null;
        }

        return ($day !== false && $day->format('Y-m-d') === $value) ? $day : null;
    }
}
