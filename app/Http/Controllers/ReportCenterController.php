<?php

namespace App\Http\Controllers;

use App\Models\Route;
use App\Support\ReportRequest;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The Report center: one place that lists every report the cooperative can generate, with the
 * period and route chosen once and carried into each report's Generate and Export links.
 *
 * It queries no figures itself. The route sits in the tenant.admin group, and the only read is
 * the cooperative's own routes through the scoped Route model, for the route picker.
 */
class ReportCenterController extends Controller
{
    public function index(Request $request): View
    {
        $period = ReportRequest::fromRequest($request, CarbonImmutable::now());

        return view('reports.center', [
            'from' => $period->from,
            'to' => $period->to,
            'preset' => $period->preset,
            'routeId' => $period->routeId,
            'notice' => $period->notice,
            'periodQuery' => $period->query(),
            'routes' => Route::query()->orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }
}
