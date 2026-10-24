<?php

namespace App\Support;

use App\Models\Report;
use Carbon\CarbonInterface;
use Illuminate\Validation\Rule;

/**
 * What it takes to close a passenger report, and what closing or reopening one writes.
 *
 * "Resolved" tells the passenger the cooperative reviewed and closed their report. It does not
 * say the complaint was true, so resolving now requires an outcome (upheld or not upheld),
 * which is what lets the driver scorecard tell a proven complaint from a mistaken one instead
 * of treating every closed report as proven.
 *
 * An outcome belongs only to a resolved report. Reopening clears it, and dismissing never
 * stores one: dismissed already means "no further action". Migration 046 makes the database
 * refuse the contradiction too; this keeps the console from ever asking it to.
 *
 * Pure apart from the model it is handed, so it is tested without a database.
 */
final class ReportResolutionPolicy
{
    /** @return array<string, array<int, mixed>> */
    public static function rules(): array
    {
        return [
            'status' => ['required', Rule::in(array_keys(Report::STATUSES))],
            // The Upheld/Not upheld radios are hidden, not removed, when the status changes,
            // so a stale value can arrive with any status. It is required for 'resolved' and
            // otherwise ignored by apply(), never rejected.
            'resolution_outcome' => ['nullable', 'required_if:status,resolved', Rule::in(array_keys(Report::OUTCOMES))],
        ];
    }

    public static function apply(
        Report $report,
        string $status,
        ?string $outcome,
        ?string $actorId,
        CarbonInterface $now
    ): void {
        $report->status = $status;

        if (in_array($status, Report::RESOLVED_STATES, true)) {
            // Constraint reports_resolution_fields: resolved and dismissed require resolved_at.
            $report->resolved_at = $now;
            $report->resolved_by_profile_id = $actorId;
        } else {
            // submitted and reviewing require resolved_at to be null.
            $report->resolved_at = null;
            $report->resolved_by_profile_id = null;
        }

        // Constraint reports_outcome_only_when_resolved_chk: only a resolved report has one.
        $report->resolution_outcome = $status === 'resolved' ? $outcome : null;
    }
}
