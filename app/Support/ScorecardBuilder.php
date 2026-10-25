<?php

namespace App\Support;

/**
 * Assembles the rows of the driver scorecard from plain arrays.
 *
 * Pure on purpose: it takes already-scoped data and returns display rows, and touches no
 * model, query or clock. The controller does the (organization-scoped) reading; this does the
 * deciding. That split is what lets every rule about how a person is shown be tested without
 * a database, and it is where the degenerate cases live: a driver with no trips, no name, or
 * trips that were never scored must each read as exactly what they are.
 *
 * Input shapes (the controller's queries are aliased to exactly these keys):
 *   $drivers    list of ['profile_id', 'name' (?string), 'employee_code' (?string), 'is_active' (bool)]
 *   $aggregates map profile_id => ['trips', 'minutes', 'distance_km', 'on_time', 'early', 'late',
 *                                  'no_timetable', 'unscheduled', 'partial',
 *                                  'avg_speed_kmh' (?float), 'max_speed_kmh' (?float),
 *                                  'overspeed_share' (?float)]
 *   $reports    list of ['driver_id', 'category', 'status', 'outcome' (?string), 'is_anonymous' (bool)]
 *
 * Rows come back ordered by name. Never by complaints: a scorecard that sorts by complaints
 * is a leaderboard, and this one must not be.
 */
final class ScorecardBuilder
{
    /**
     * @param  array<int, array<string, mixed>>  $drivers
     * @param  array<string, array<string, mixed>>  $aggregates
     * @param  array<int, array<string, mixed>>  $reports
     * @return array<int, array<string, mixed>>
     */
    public static function build(array $drivers, array $aggregates, array $reports): array
    {
        $byDriver = [];
        foreach ($reports as $report) {
            $byDriver[$report['driver_id']][] = $report;
        }

        $rows = [];
        foreach ($drivers as $driver) {
            $id = $driver['profile_id'];
            $agg = $aggregates[$id] ?? null;
            $trips = (int) ($agg['trips'] ?? 0);

            // A driver who is no longer active and did nothing in the period is noise. One who
            // is inactive but worked in the period stays, because the numbers are still theirs.
            if (! $driver['is_active'] && $trips === 0) {
                continue;
            }

            $rows[] = self::row($driver, $agg ?? [], $byDriver[$id] ?? []);
        }

        usort($rows, static fn (array $a, array $b): int => [mb_strtolower($a['name']), $a['driver_id']]
            <=> [mb_strtolower($b['name']), $b['driver_id']]);

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $driver
     * @param  array<string, mixed>  $agg
     * @param  array<int, array<string, mixed>>  $reports
     * @return array<string, mixed>
     */
    private static function row(array $driver, array $agg, array $reports): array
    {
        $trips = (int) ($agg['trips'] ?? 0);
        $onTime = (int) ($agg['on_time'] ?? 0);
        $early = (int) ($agg['early'] ?? 0);
        $late = (int) ($agg['late'] ?? 0);
        $scored = $onTime + $early + $late;

        $complaints = self::complaints($reports);

        return [
            'driver_id' => $driver['profile_id'],
            'name' => self::displayName($driver),
            'employee_code' => $driver['employee_code'] ?? null,

            'trips' => $trips,
            'hours' => round(((int) ($agg['minutes'] ?? 0)) / 60, 1),
            // null, not 0: a driver whose GPS was purged has no measured distance, which is not zero km.
            'distance_km' => isset($agg['distance_km']) ? round((float) $agg['distance_km'], 1) : null,

            'scored' => $scored,
            'on_time' => $onTime,
            'early' => $early,
            'late' => $late,
            'on_time_pct' => ScorecardPolicy::onTimePercent($onTime, $scored),
            'no_timetable' => (int) ($agg['no_timetable'] ?? 0),
            'unscheduled' => (int) ($agg['unscheduled'] ?? 0),

            'avg_speed_kmh' => $agg['avg_speed_kmh'] ?? null,
            'max_speed_kmh' => $agg['max_speed_kmh'] ?? null,
            'overspeed_share' => $agg['overspeed_share'] ?? null,
            'partial' => (int) ($agg['partial'] ?? 0),

            'complaints' => $complaints['counts'],
            'safety_related' => $complaints['safety'],
            'rate_per_100' => ScorecardPolicy::ratePer100($complaints['counts']['total'], $trips),
            'anonymous_share' => $complaints['counts']['total'] > 0
                ? round($complaints['anonymous'] / $complaints['counts']['total'], 2)
                : null,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $reports
     * @return array{counts: array<string, int>, safety: int, anonymous: int}
     */
    private static function complaints(array $reports): array
    {
        $counts = [
            'received' => 0, 'under_review' => 0, 'upheld' => 0, 'not_upheld' => 0,
            'outcome_not_recorded' => 0, 'dismissed' => 0, 'total' => 0,
        ];
        $safety = 0;
        $anonymous = 0;

        foreach ($reports as $report) {
            switch (ScorecardPolicy::complaintBucket($report['category'])) {
                case 'driver':
                    $counts[ScorecardPolicy::statusBucket($report['status'], $report['outcome'] ?? null)]++;
                    $counts['total']++;
                    if (! empty($report['is_anonymous'])) {
                        $anonymous++;
                    }
                    break;
                case 'safety':
                    $safety++;
                    break;
            }
        }

        return ['counts' => $counts, 'safety' => $safety, 'anonymous' => $anonymous];
    }

    /** The name, else the employee code, else a label. Never blank. */
    private static function displayName(array $driver): string
    {
        foreach (['name', 'employee_code'] as $key) {
            $value = isset($driver[$key]) ? trim((string) $driver[$key]) : '';
            if ($value !== '') {
                return $value;
            }
        }

        return 'Unnamed driver';
    }
}
