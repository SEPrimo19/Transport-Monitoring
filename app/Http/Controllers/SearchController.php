<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Driver;
use App\Models\Report;
use App\Models\Route;
use App\Models\Terminal;
use App\Models\Trip;
use App\Models\TripSchedule;
use App\Models\Organization;
use App\Models\Vehicle;
use App\Providers\AppServiceProvider;
use App\Support\AdminContext;
use Illuminate\Support\Facades\Cache;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * One search box over everything the admin can already reach: vehicles, drivers,
 * terminals, routes, schedules, assignments, trips and reports.
 *
 * TENANT ISOLATION — three independent layers, because this is the one screen that
 * touches every table at once:
 *
 *   1. The route sits inside the `tenant.admin` group, so a super_admin never reaches
 *      it. That mirrors the eight screens it searches, which are gated the same way:
 *      a platform operator is not meant to browse a cooperative's data.
 *   2. Every query below runs through an Eloquent model carrying
 *      {@see \App\Models\Concerns\BelongsToOrganization}, whose global scope adds
 *      `organization_id = <this admin's org>` and FAILS CLOSED (`1 = 0`) with no org
 *      context. Relation constraints go through whereHas, so the related query carries
 *      the scope too -- a vehicle plate can only match a vehicle in the same org.
 *   3. No raw DB::table() anywhere in this file. That is the one way to lose layer 2,
 *      so the rule is absolute rather than case-by-case.
 *
 * Each group is capped. A search is for finding one thing, not for exporting a table,
 * and an uncapped eight-way query on a free-tier dyno is a slow page for no benefit.
 */
class SearchController extends Controller
{
    /** Results shown per entity type before "see all". */
    private const PER_GROUP = 8;

    /** Long enough for a plate, a name or a route code; bounds the LIKE pattern. */
    private const MAX_TERM = 100;

    public function index(Request $request, AdminContext $ctx): View
    {
        $term = trim((string) $request->query('q', ''));
        $term = $term === '' ? null : mb_substr($term, 0, self::MAX_TERM);

        return view('search.index', [
            'term' => $term,
            'groups' => $term === null ? [] : $this->search($term),
            // The composer that supplies $orgName is bound to layouts.app, so it is not
            // in scope inside this view's own @section. ReportController::show passes it
            // by hand for the same reason.
            'orgName' => $this->organizationName($ctx),
        ]);
    }

    /**
     * The admin's own cooperative, for the "searched ... in X" line.
     *
     * Reuses the exact cache entry the layout's chrome composer already fills, so this
     * costs no query on a normal page view and stays correct after a rebrand (the org
     * profile update forgets that key).
     */
    private function organizationName(AdminContext $ctx): ?string
    {
        if ($ctx->organizationId === null) {
            return null;
        }

        return Cache::remember(
            AppServiceProvider::orgChromeCacheKey($ctx->organizationId),
            now()->addMinutes(10),
            fn () => Organization::find($ctx->organizationId)
        )?->name;
    }

    /**
     * @return array<int, array{key:string, label:string, icon:string, index:string, rows:array<int, array{title:string, meta:string, url:string}>, total:int}>
     */
    private function search(string $term): array
    {
        // addcslashes, not a bare term: an admin typing "100%" means those four
        // characters. The value is bound either way, so this is about matching
        // correctly, not about injection.
        $like = '%'.addcslashes($term, '%_\\').'%';

        $groups = [];

        $groups[] = $this->group('vehicles', 'Vehicles', 'bi-bus-front', route('vehicles.index'),
            Vehicle::query()->where(fn (Builder $q) => $q
                ->where('plate_number', 'ilike', $like)
                ->orWhere('fleet_code', 'ilike', $like)),
            fn (Vehicle $v) => [
                'title' => $v->plate_number ?? '—',
                'meta' => trim(($v->fleet_code ? 'Body '.$v->fleet_code.' · ' : '').ucfirst((string) $v->status)),
                'url' => route('vehicles.edit', $v),
            ]);

        $groups[] = $this->group('drivers', 'Drivers', 'bi-person-badge', route('drivers.index'),
            Driver::query()->with('profile')->where(fn (Builder $q) => $q
                ->where('employee_code', 'ilike', $like)
                ->orWhere('license_number', 'ilike', $like)
                ->orWhere('phone', 'ilike', $like)
                ->orWhereHas('profile', fn (Builder $p) => $p->where('full_name', 'ilike', $like))),
            fn (Driver $d) => [
                'title' => $d->profile?->full_name ?? $d->employee_code ?? '—',
                'meta' => trim(($d->employee_code ? $d->employee_code.' · ' : '').($d->is_active ? 'Active' : 'Inactive')),
                'url' => route('drivers.edit', $d),
            ]);

        $groups[] = $this->group('terminals', 'Terminals', 'bi-pin-map', route('terminals.index'),
            Terminal::query()->where(fn (Builder $q) => $q
                ->where('code', 'ilike', $like)
                ->orWhere('name', 'ilike', $like)
                ->orWhere('address', 'ilike', $like)),
            fn (Terminal $t) => [
                'title' => $t->name ?? $t->code ?? '—',
                'meta' => trim((string) $t->code.($t->address ? ' · '.$t->address : '')),
                'url' => route('terminals.edit', $t),
            ]);

        $groups[] = $this->group('routes', 'Routes', 'bi-signpost-2', route('routes.index'),
            Route::query()->with(['originTerminal', 'destinationTerminal'])->where(fn (Builder $q) => $q
                ->where('code', 'ilike', $like)
                ->orWhere('name', 'ilike', $like)),
            fn (Route $r) => [
                'title' => $r->name ?? $r->code ?? '—',
                'meta' => trim((string) $r->code.' · '.($r->originTerminal?->code ?? '—').' → '.($r->destinationTerminal?->code ?? '—')),
                'url' => route('routes.edit', $r),
            ]);

        $groups[] = $this->group('schedules', 'Schedules', 'bi-calendar-week', route('schedules.index'),
            TripSchedule::query()->with('route')->where(fn (Builder $q) => $q
                ->where('label', 'ilike', $like)
                ->orWhereHas('route', fn (Builder $r) => $r
                    ->where('code', 'ilike', $like)
                    ->orWhere('name', 'ilike', $like))),
            fn (TripSchedule $s) => [
                'title' => $s->label ?: ($s->route?->code ?? 'Schedule'),
                'meta' => trim(($s->route?->code ? $s->route->code.' · ' : '').$s->daysLabel()),
                'url' => route('schedules.edit', $s),
            ]);

        $groups[] = $this->group('assignments', 'Assignments', 'bi-clipboard-check', route('assignments.index'),
            Assignment::query()->with(['driver.profile', 'vehicle', 'route'])->where(fn (Builder $q) => $q
                ->whereHas('driver.profile', fn (Builder $p) => $p->where('full_name', 'ilike', $like))
                ->orWhereHas('vehicle', fn (Builder $v) => $v
                    ->where('plate_number', 'ilike', $like)
                    ->orWhere('fleet_code', 'ilike', $like))
                ->orWhereHas('route', fn (Builder $r) => $r->where('code', 'ilike', $like))),
            fn (Assignment $a) => [
                'title' => ($a->driver?->profile?->full_name ?? 'Driver').' · '.($a->vehicle?->plate_number ?? 'Vehicle'),
                'meta' => trim(($a->route?->code ?? '—').' · '.($a->is_active ? 'Active' : 'Inactive')),
                'url' => route('assignments.edit', $a),
            ]);

        $groups[] = $this->group('trips', 'Trips', 'bi-clock-history', route('trips.index'),
            Trip::query()->with(['driver.profile', 'vehicle', 'route'])->where(fn (Builder $q) => $q
                ->whereHas('vehicle', fn (Builder $v) => $v
                    ->where('plate_number', 'ilike', $like)
                    ->orWhere('fleet_code', 'ilike', $like))
                ->orWhereHas('route', fn (Builder $r) => $r
                    ->where('code', 'ilike', $like)
                    ->orWhere('name', 'ilike', $like))
                ->orWhereHas('driver.profile', fn (Builder $p) => $p->where('full_name', 'ilike', $like)))
                ->orderByRaw('coalesce(ended_at, started_at, created_at) desc'),
            fn (Trip $t) => [
                'title' => ($t->vehicle?->plate_number ?? 'Trip').' · '.($t->route?->code ?? '—'),
                'meta' => implode(' · ', array_filter([$t->statusLabel(), $t->started_at?->format('d M Y, H:i')])),
                'url' => route('trips.show', $t),
            ]);

        $groups[] = $this->group('reports', 'Reports', 'bi-flag', route('reports.index'),
            Report::query()->with(['vehicle', 'route'])->where(fn (Builder $q) => $q
                ->where('category', 'ilike', $like)
                ->orWhere('description', 'ilike', $like)
                ->orWhereHas('vehicle', fn (Builder $v) => $v->where('plate_number', 'ilike', $like))
                ->orWhereHas('route', fn (Builder $r) => $r->where('code', 'ilike', $like)))
                ->orderByDesc('created_at'),
            fn (Report $r) => [
                'title' => $r->categoryLabel(),
                'meta' => implode(' · ', array_filter([$r->statusLabel(), $r->vehicle?->plate_number, $r->created_at?->format('d M Y')])),
                'url' => route('reports.show', $r),
            ]);

        // Groups with nothing in them are dropped rather than rendered empty: eight
        // "No matches" headings is noise around the one heading that matters.
        return array_values(array_filter($groups, fn (array $g) => $g['total'] > 0));
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @param  callable(mixed): array{title:string, meta:string, url:string}  $present
     * @return array{key:string, label:string, icon:string, index:string, rows:array<int, array{title:string, meta:string, url:string}>, total:int}
     */
    private function group(string $key, string $label, string $icon, string $index, Builder $query, callable $present): array
    {
        // count() before the limit, so "showing 8 of 23" is honest. Two queries per
        // group rather than one, which is the price of not lying about the total.
        $total = (clone $query)->count();
        $rows = $total === 0 ? [] : $query->limit(self::PER_GROUP)->get()->map($present)->all();

        return [
            'key' => $key,
            'label' => $label,
            'icon' => $icon,
            'index' => $index,
            'rows' => $rows,
            'total' => $total,
        ];
    }
}
