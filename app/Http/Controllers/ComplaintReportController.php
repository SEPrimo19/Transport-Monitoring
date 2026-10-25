<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Report;
use App\Models\Route;
use App\Support\AdminContext;
use App\Support\ComplaintReportBuilder;
use App\Support\CsvReport;
use App\Support\ReportRequest;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The printable complaints report: how many passenger reports came in over a period, what they
 * were about, how they ended and how long closing them took.
 *
 * Privacy is the point of this class's shape. It selects six columns and none of them says who
 * complained, what they wrote or which vehicle or driver it was about, so none of that can reach
 * the page or the CSV. ComplaintReportIsolationTest fails if an identifying column is ever added
 * to this file. It is read-only and tenant-only: the routes sit in tenant.admin and every read
 * goes through a model carrying BelongsToOrganization (Report, Route).
 *
 * The page and the CSV both call data(), so they cannot disagree about a number.
 */
class ComplaintReportController extends Controller
{
    private const SCOPE_TEXT = [
        'driver' => 'Driver conduct',
        'safety' => 'Safety, shown separately',
        'not_driver' => 'Not about a driver',
    ];

    public function report(Request $request, AdminContext $ctx): View
    {
        return view('reports.complaints', $this->data($request) + [
            'organization' => $ctx->organizationId === null ? null : Organization::find($ctx->organizationId),
            'generatedBy' => $ctx->fullName ?: ($ctx->email ?: 'an administrator'),
            'generatedAt' => CarbonImmutable::now(),
            'scopeText' => self::SCOPE_TEXT,
        ]);
    }

    /**
     * One CSV carrying the three tables, told apart by the Section column. Cells that do not
     * apply to a row are empty.
     */
    public function export(Request $request): StreamedResponse
    {
        $data = $this->data($request);

        $rows = static function () use ($data): \Generator {
            foreach ($data['byCategory'] as $c) {
                yield ['By category', $c['label'], $c['received'], $c['closed'], $c['open'], self::SCOPE_TEXT[$c['scope']]];
            }
            foreach ($data['byStatus'] as $s) {
                yield ['By status', $s['label'], $s['count'], null, null, null];
            }
            foreach ($data['byRoute'] as $r) {
                yield ['By route', $r['route_code'], $r['received'], $r['closed'], $r['open'], null];
            }
        };

        return CsvReport::stream(
            'complaints-'.$data['from']->format('Y-m-d').'-to-'.$data['to']->format('Y-m-d').'.csv',
            ['Section', 'Item', 'Received', 'Closed', 'Still open', 'Scope'],
            $rows()
        );
    }

    /** @return array<string, mixed> */
    public function data(Request $request): array
    {
        $period = ReportRequest::fromRequest($request, CarbonImmutable::now());

        // Reports are low volume, so they are aggregated in PHP over one light query. Six
        // columns, none identifying.
        $reports = Report::query()
            ->whereBetween('created_at', [$period->from, $period->to])
            ->when($period->routeId, static fn ($q) => $q->where('route_id', $period->routeId))
            ->get(['category', 'status', 'resolution_outcome', 'route_id', 'created_at', 'resolved_at'])
            ->map(static fn (Report $r): array => [
                'category' => (string) $r->category,
                'status' => (string) $r->status,
                'outcome' => $r->resolution_outcome === null ? null : (string) $r->resolution_outcome,
                'route_id' => $r->route_id === null ? null : (string) $r->route_id,
                'created_at' => CarbonImmutable::instance($r->created_at),
                'resolved_at' => $r->resolved_at === null ? null : CarbonImmutable::instance($r->resolved_at),
            ])->all();

        $routes = Route::query()->orderBy('code')->get(['id', 'code', 'name'])
            ->mapWithKeys(static fn (Route $r): array => [
                (string) $r->id => ['code' => (string) $r->code, 'name' => (string) $r->name],
            ])->all();
        $chosen = $period->routeId === null ? null : ($routes[$period->routeId] ?? null);

        return ComplaintReportBuilder::build($reports, $routes) + [
            'from' => $period->from,
            'to' => $period->to,
            'routeLabel' => $chosen === null ? 'All routes' : $chosen['code'].' · '.$chosen['name'],
            'notice' => $period->notice,
            'periodQuery' => $period->query(),
        ];
    }
}
