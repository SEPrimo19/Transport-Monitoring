<?php

namespace App\Http\Controllers;

use App\Http\Requests\TripScheduleRequest;
use App\Models\Organization;
use App\Models\Route;
use App\Models\TripSchedule;
use App\Support\AdminContext;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Org-scoped route timetables (trip_schedules). Informational — publishing a
 * schedule does not create trips. Reads and {schedule} binding go through
 * OrganizationScope.
 */
class TripScheduleController extends Controller
{
    public function index(AdminContext $ctx): View
    {
        $schedules = TripSchedule::query()
            ->with('route')
            ->orderBy('route_id')
            ->orderByDesc('is_active')
            ->get();

        $orgNames = $ctx->isSuperAdmin()
            ? Organization::whereIn('id', $schedules->pluck('organization_id')->filter()->unique()->all())
                ->pluck('name', 'id')
            : collect();

        return view('schedules.index', ['schedules' => $schedules, 'orgNames' => $orgNames]);
    }

    public function create(AdminContext $ctx): View
    {
        return view('schedules.create', [
            'schedule' => new TripSchedule(['mode' => 'interval', 'is_active' => true, 'days_of_week' => [1, 2, 3, 4, 5, 6]]),
        ] + $this->formOptions($ctx));
    }

    public function store(TripScheduleRequest $request): RedirectResponse
    {
        $schedule = TripSchedule::create($request->validated());

        return redirect()->route('schedules.index')
            ->with('status', 'Schedule for '.($schedule->route?->code ?? 'route').' was added.');
    }

    public function edit(TripSchedule $schedule, AdminContext $ctx): View
    {
        return view('schedules.edit', ['schedule' => $schedule] + $this->formOptions($ctx, $schedule));
    }

    public function update(TripScheduleRequest $request, TripSchedule $schedule): RedirectResponse
    {
        $schedule->update($request->validated());

        return redirect()->route('schedules.index')->with('status', 'Schedule was updated.');
    }

    public function destroy(TripSchedule $schedule): RedirectResponse
    {
        try {
            $schedule->delete();
        } catch (QueryException) {
            return redirect()->route('schedules.index')->with('error', 'Could not delete that schedule.');
        }

        return redirect()->route('schedules.index')->with('status', 'Schedule was deleted.');
    }

    /** @return array{routes: Collection, organizations: Collection} */
    private function formOptions(AdminContext $ctx, ?TripSchedule $schedule = null): array
    {
        return [
            // organization_id is needed so the form can group routes by org for super_admin.
            //
            // Only ACTIVE routes are offered: a timetable on a retired route publishes
            // departures to passengers that no driver can legally run (migration
            // 20260814_027 blocks trip start on an inactive route). The schedule being
            // edited keeps its own route so saving cannot silently re-point it.
            'routes' => Route::query()
                ->where(function ($q) use ($schedule) {
                    $q->where('is_active', true);
                    if ($schedule?->route_id) {
                        $q->orWhere('id', $schedule->route_id);
                    }
                })
                ->orderBy('code')->get(['id', 'code', 'name', 'organization_id']),
            'organizations' => $ctx->isSuperAdmin()
                ? Organization::orderBy('name')->get(['id', 'name'])
                : collect(),
        ];
    }
}
