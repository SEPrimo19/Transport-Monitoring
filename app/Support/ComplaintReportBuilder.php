<?php

namespace App\Support;

use App\Models\Report;
use Carbon\CarbonInterface;

/**
 * The figures and tables of the printed complaints report.
 *
 * Pure: arrays in, arrays out, no database. It holds the rules that keep the report honest:
 *
 *  - `resolved` is never `upheld` without a recorded outcome: that is ScorecardPolicy's rule
 *    and it is reused here, so a resolved report with no outcome reads "Outcome not recorded";
 *  - only driver conduct is labelled as about a driver (ScorecardPolicy::complaintBucket);
 *  - a time nobody measured is null, never 0, and a count of zero is a real count;
 *  - nothing here receives a complainant, a description or a vehicle, so none can leak: the
 *    input shape is the whole of what the report knows about a complaint.
 *
 * Closed means resolved or dismissed; open means submitted or reviewing.
 */
final class ComplaintReportBuilder
{
    public const UNLINKED = 'Not linked to a route';

    /** Fixed display order of the status table. */
    private const STATUS_BUCKETS = [
        'received' => 'Pending',
        'under_review' => 'Under review',
        'upheld' => 'Upheld',
        'not_upheld' => 'Not upheld',
        'outcome_not_recorded' => 'Outcome not recorded',
        'dismissed' => 'Dismissed',
    ];

    /**
     * @param  array<int, array{category: string, status: string, outcome: ?string, route_id: ?string, created_at: CarbonInterface, resolved_at: ?CarbonInterface}>  $reports
     * @param  array<string, array{code: string, name: string}>  $routes  route id => code and name
     * @return array{figures: array<string, mixed>, byCategory: array<int, array<string, mixed>>, byStatus: array<int, array<string, mixed>>, byRoute: array<int, array<string, mixed>>}
     */
    public static function build(array $reports, array $routes): array
    {
        $closed = 0;
        $open = 0;
        $minutes = [];

        $category = [];
        foreach (array_keys(Report::CATEGORIES) as $key) {
            $category[$key] = ['received' => 0, 'closed' => 0, 'open' => 0];
        }
        $status = array_fill_keys(array_keys(self::STATUS_BUCKETS), 0);
        $route = [];
        $unlinked = ['received' => 0, 'closed' => 0, 'open' => 0];

        foreach ($reports as $r) {
            $isClosed = in_array($r['status'], Report::RESOLVED_STATES, true);
            $isClosed ? $closed++ : $open++;

            if ($isClosed && $r['resolved_at'] !== null) {
                $minutes[] = max(0, (int) floor($r['created_at']->diffInSeconds($r['resolved_at'], false) / 60));
            }

            $cat = array_key_exists($r['category'], Report::CATEGORIES) ? $r['category'] : 'other';
            self::tally($category[$cat], $isClosed);

            $status[ScorecardPolicy::statusBucket($r['status'], $r['outcome'])]++;

            $routeId = $r['route_id'];
            if ($routeId !== null && isset($routes[$routeId])) {
                $route[$routeId] ??= ['received' => 0, 'closed' => 0, 'open' => 0];
                self::tally($route[$routeId], $isClosed);
            } else {
                self::tally($unlinked, $isClosed);
            }
        }

        $byCategory = [];
        foreach (Report::CATEGORIES as $key => $label) {
            $byCategory[] = ['category' => $key, 'label' => $label, 'scope' => ScorecardPolicy::complaintBucket($key)] + $category[$key];
        }

        $byStatus = [];
        foreach (self::STATUS_BUCKETS as $bucket => $label) {
            $byStatus[] = ['bucket' => $bucket, 'label' => $label, 'count' => $status[$bucket]];
        }

        $byRoute = [];
        foreach ($route as $id => $counts) {
            $byRoute[] = ['route_code' => $routes[$id]['code'], 'route_name' => $routes[$id]['name']] + $counts;
        }
        usort($byRoute, static fn (array $x, array $y): int => [mb_strtolower($x['route_code']), $x['route_name']]
            <=> [mb_strtolower($y['route_code']), $y['route_name']]);
        if ($unlinked['received'] > 0) {
            $byRoute[] = ['route_code' => self::UNLINKED, 'route_name' => ''] + $unlinked;
        }

        return [
            'figures' => [
                'received' => count($reports),
                'closed' => $closed,
                'open' => $open,
                'median_close_minutes' => self::median($minutes),
                'slowest_close_minutes' => $minutes === [] ? null : max($minutes),
            ],
            'byCategory' => $byCategory,
            'byStatus' => $byStatus,
            'byRoute' => $byRoute,
        ];
    }

    /** @param array{received: int, closed: int, open: int} $counts */
    private static function tally(array &$counts, bool $isClosed): void
    {
        $counts['received']++;
        $counts[$isClosed ? 'closed' : 'open']++;
    }

    /** @param array<int, int> $values */
    private static function median(array $values): ?int
    {
        if ($values === []) {
            return null;
        }

        sort($values);
        $n = count($values);
        $mid = intdiv($n, 2);

        return $n % 2 === 1 ? $values[$mid] : (int) round(($values[$mid - 1] + $values[$mid]) / 2);
    }
}
