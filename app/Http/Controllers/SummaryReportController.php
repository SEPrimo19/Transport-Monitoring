<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Support\AdminContext;
use App\Support\CsvReport;
use App\Support\DriverReportSummary;
use App\Support\SummaryReportBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The dashboard summary: one printable page that gathers the headline figures of the Trips,
 * Complaints, Vehicles and routes and Driver performance reports for one period, for a board
 * pack.
 *
 * It runs no query of its own. Every figure comes from an individual report's own data method,
 * so the summary cannot disagree with them, and each of those already reads only through scoped
 * models. SummaryReportIsolationTest fails if a query is ever added here, or if a person's name
 * could reach the page: a board pack states what the cooperative did, not what named people did.
 * The Drivers area is aggregate figures only.
 *
 * The routes sit in the tenant.admin group. The page and the CSV share data(), and the CSV is
 * the same list of measures as the key figures, one row each.
 */
class SummaryReportController extends Controller
{
    public function __construct(
        private TripReportController $trips,
        private ComplaintReportController $complaints,
        private FleetReportController $fleet,
        private DriverPerformanceController $drivers,
    ) {}

    public function report(Request $request, AdminContext $ctx): View
    {
        return view('reports.summary', $this->data($request, $ctx) + [
            'organization' => $ctx->organizationId === null ? null : Organization::find($ctx->organizationId),
            'generatedBy' => $ctx->fullName ?: ($ctx->email ?: 'an administrator'),
            'generatedAt' => CarbonImmutable::now(),
        ]);
    }

    /** One row per measure; a figure nobody measured is an empty cell, never 0. */
    public function export(Request $request, AdminContext $ctx): StreamedResponse
    {
        $data = $this->data($request, $ctx);

        $rows = static function () use ($data): \Generator {
            foreach ($data['measures'] as $m) {
                yield [$m['area'], $m['measure'], $m['value']];
            }
        };

        return CsvReport::stream(
            'summary-'.$data['from']->format('Y-m-d').'-to-'.$data['to']->format('Y-m-d').'.csv',
            ['Area', 'Measure', 'Value'],
            $rows()
        );
    }

    /** @return array<string, mixed> */
    private function data(Request $request, AdminContext $ctx): array
    {
        $trips = $this->trips->data($request);
        $complaints = $this->complaints->data($request);
        $fleet = $this->fleet->data($request);
        $drivers = DriverReportSummary::figures($this->drivers->scorecard($request, $ctx)['rows']);

        $measures = SummaryReportBuilder::measures($trips, $complaints, $fleet, $drivers);

        return [
            'measures' => $measures,
            'headline' => SummaryReportBuilder::headline($measures),
            'trips' => $trips,
            'complaints' => $complaints,
            'fleet' => $fleet,
            'drivers' => $drivers,
            'from' => $trips['from'],
            'to' => $trips['to'],
            'routeLabel' => $trips['routeLabel'],
            'notice' => $trips['notice'],
            'periodQuery' => $trips['periodQuery'],
            'timezone' => $trips['timezone'],
        ];
    }
}
