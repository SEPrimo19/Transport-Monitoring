<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Report;
use App\Support\AdminContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Passenger reports — VIEW + TRIAGE only. Org-scoped reads; no create/delete
 * (reports originate in the mobile app; the audit trail is kept). Triage changes
 * status and, per the reports_resolution_fields DB constraint, sets resolved_at +
 * resolved_by when resolving/dismissing and clears them when reopening.
 */
class ReportController extends Controller
{
    public function index(Request $request, AdminContext $ctx): View
    {
        // Optional status filter, whitelisted against the enum.
        $status = $request->query('status');
        if (! array_key_exists($status, Report::STATUSES)) {
            $status = null;
        }

        $reports = Report::query()
            ->with(['reporter', 'vehicle', 'route'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->get();

        $orgNames = $ctx->isSuperAdmin()
            ? Organization::whereIn('id', $reports->pluck('organization_id')->filter()->unique()->all())
                ->pluck('name', 'id')
            : collect();

        // Counts per status for the filter chips (unfiltered).
        $counts = Report::query()
            ->selectRaw('status, count(*) as n')
            ->groupBy('status')
            ->pluck('n', 'status');

        return view('reports.index', [
            'reports' => $reports,
            'activeStatus' => $status,
            'counts' => $counts,
            'orgNames' => $orgNames,
        ]);
    }

    public function show(Report $report, AdminContext $ctx): View
    {
        $report->load(['reporter', 'resolvedBy', 'vehicle', 'route']);

        $orgName = $ctx->isSuperAdmin() && $report->organization_id
            ? Organization::whereKey($report->organization_id)->value('name')
            : null;

        return view('reports.show', [
            'report' => $report,
            'orgName' => $orgName,
            'evidenceUrl' => $report->evidence_object_path
                ? $this->signedEvidenceUrl($report->evidence_object_path)
                : null,
        ]);
    }

    /**
     * A short-lived signed URL for a private report-evidence object, created with
     * the admin's own GoTrue token via the Storage API. Access is gated by the
     * "report evidence admin read" storage policy (migration 017) — org-scoped, no
     * service_role. Returns null on any failure so the view degrades to metadata.
     */
    private function signedEvidenceUrl(string $path): ?string
    {
        $token = session('supabase_token');
        if (! $token) {
            return null;
        }

        $base = rtrim(config('supabase.url'), '/');
        $encoded = collect(explode('/', $path))
            ->map(fn ($seg) => rawurlencode($seg))
            ->implode('/');

        try {
            $resp = Http::withToken($token)
                ->withHeaders(['apikey' => config('supabase.anon_key')])
                ->timeout(10)
                ->post("{$base}/storage/v1/object/sign/report-evidence/{$encoded}", ['expiresIn' => 3600]);

            if ($resp->successful() && ($signed = $resp->json('signedURL'))) {
                return $base.'/storage/v1'.$signed;
            }
        } catch (\Throwable $e) {
            // fall through — the view shows the filename + a "couldn't load" note
        }

        return null;
    }

    public function update(Request $request, Report $report, AdminContext $ctx): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Report::STATUSES))],
        ]);

        $report->status = $data['status'];

        if (in_array($data['status'], Report::RESOLVED_STATES, true)) {
            // Constraint: resolved/dismissed require resolved_at.
            $report->resolved_at = now();
            $report->resolved_by_profile_id = $ctx->userId;
        } else {
            // Constraint: submitted/reviewing require resolved_at to be null.
            $report->resolved_at = null;
            $report->resolved_by_profile_id = null;
        }

        $report->save();

        return redirect()->route('reports.show', $report)
            ->with('status', "Report marked as {$report->statusLabel()}.");
    }
}
