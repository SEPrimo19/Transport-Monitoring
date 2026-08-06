<?php

namespace App\Support;

use App\Models\Report;
use Illuminate\Support\Collection;

/**
 * The passenger reports still awaiting triage — the notification bell's only source.
 *
 * Shared deliberately by the layout's view composer (first paint) and
 * {@see \App\Http\Controllers\NotificationController} (the poll that keeps it
 * current). Both render from {@see payload()}, so the two can't drift: a bell whose
 * count depends on how the page was filled is worse than no bell.
 *
 * "Pending" = status 'submitted', the mobile app's initial state. Moving a report to
 * reviewing/resolved/dismissed drops it out of here, so the badge clears itself with
 * no per-admin read-state column to maintain.
 *
 * Org scoping is automatic: Report uses BelongsToOrganization, whose global
 * OrganizationScope narrows an org_admin to their own tenant and leaves a super_admin
 * cross-org. Nothing here may filter by organization by hand.
 *
 * ONE query, on purpose. This runs on every page render of the admin shell, and the
 * database is a hosted Supabase instance ~400 ms away, so each round trip is visible
 * to the user. The count arrives as a window function over the same result set that
 * produces the preview rows, and the vehicle/route labels are joined rather than
 * eager-loaded — what was 4 round trips is now 1.
 */
class PendingReportFeed
{
    /** Entries the bell dropdown lists before "View all reports". */
    public const PREVIEW_LIMIT = 5;

    /** Memoized for the request: the composer and the endpoint must not both query. */
    private ?array $payload = null;

    /**
     * The bell's state as a JSON-safe array. This is the contract BOTH the Blade in
     * layouts/app.blade.php and the poller's DOM building consume — keep the keys in
     * step with both.
     */
    public function payload(): array
    {
        if ($this->payload !== null) {
            return $this->payload;
        }

        $rows = $this->fetch();

        return $this->payload = [
            // count(*) over () is evaluated before LIMIT, so this is the FULL pending
            // total, not the size of the preview. No rows at all means none pending.
            'count' => (int) ($rows->first()->total_pending ?? 0),
            'items' => $rows->map(fn (Report $report) => [
                'id' => $report->getKey(),
                'url' => route('reports.show', $report),
                'title' => $report->categoryLabel(),
                'subtitle' => $report->vehicle_plate ?: ($report->route_name ?: 'Unassigned'),
                'age' => optional($report->created_at)->diffForHumans(null, true) ?? '',
            ])->values()->all(),
        ];
    }

    private function fetch(): Collection
    {
        return Report::query()
            ->where('reports.status', 'submitted')
            // Joined, not eager-loaded: ->with() would cost a round trip per relation
            // for two columns. Both tables are reached by primary key, and the rows are
            // already org-scoped by OrganizationScope on `reports`.
            ->leftJoin('vehicles', 'vehicles.id', '=', 'reports.vehicle_id')
            ->leftJoin('routes', 'routes.id', '=', 'reports.route_id')
            ->selectRaw(
                'reports.id, reports.category, reports.created_at,'
                .' vehicles.plate_number as vehicle_plate, routes.name as route_name,'
                .' count(*) over () as total_pending'
            )
            ->orderByDesc('reports.created_at')
            ->limit(self::PREVIEW_LIMIT)
            ->get();
    }
}
