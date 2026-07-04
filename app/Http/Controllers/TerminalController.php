<?php

namespace App\Http\Controllers;

use App\Http\Requests\TerminalRequest;
use App\Models\Organization;
use App\Models\Terminal;
use App\Support\AdminContext;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Org-scoped terminal management. Terminals are the endpoints a route attaches to
 * (origin/destination), so they must exist before a route can reference them.
 * Reads and route-model binding go through OrganizationScope (own org for
 * org_admin, all for super_admin, fail-closed with no context).
 */
class TerminalController extends Controller
{
    public function index(AdminContext $ctx): View
    {
        $terminals = Terminal::query()->orderBy('code')->get();

        // How many routes reference each terminal (either end) — drives the "in use"
        // hint and whether delete is allowed. One grouped query, no N+1.
        $routeUse = $this->routeUsageCounts($terminals->pluck('id')->all());

        $orgNames = $ctx->isSuperAdmin()
            ? Organization::whereIn('id', $terminals->pluck('organization_id')->filter()->unique()->all())
                ->pluck('name', 'id')
            : collect();

        return view('terminals.index', [
            'terminals' => $terminals,
            'routeUse' => $routeUse,
            'orgNames' => $orgNames,
        ]);
    }

    public function create(AdminContext $ctx): View
    {
        return view('terminals.create', [
            'terminal' => new Terminal(['is_active' => true]),
            'organizations' => $this->organizationsFor($ctx),
        ]);
    }

    public function store(TerminalRequest $request): RedirectResponse
    {
        // organization_id: absent for org_admin (trait stamps it); set from the
        // validated selector for super_admin.
        $terminal = Terminal::create($request->validated());

        return redirect()->route('terminals.index')
            ->with('status', "Terminal \u{201C}{$terminal->code}\u{201D} was added.");
    }

    public function edit(Terminal $terminal, AdminContext $ctx): View
    {
        return view('terminals.edit', [
            'terminal' => $terminal,
            'organizations' => $this->organizationsFor($ctx),
        ]);
    }

    public function update(TerminalRequest $request, Terminal $terminal): RedirectResponse
    {
        $terminal->update($request->validated());

        return redirect()->route('terminals.index')
            ->with('status', "Terminal \u{201C}{$terminal->code}\u{201D} was updated.");
    }

    public function destroy(Terminal $terminal): RedirectResponse
    {
        // Guard: a terminal used by any route (as origin OR destination) can't be
        // removed — the FK is RESTRICT and, more importantly, it would orphan the
        // route. Reassign or delete those routes first.
        $inUse = DB::table('routes')
            ->where('origin_terminal_id', $terminal->id)
            ->orWhere('destination_terminal_id', $terminal->id)
            ->exists();

        if ($inUse) {
            return redirect()->route('terminals.index')->with('error',
                "\u{201C}{$terminal->code}\u{201D} is used by one or more routes. Reassign or delete those routes first, or set it Inactive.");
        }

        // Belt-and-suspenders for any other FK RESTRICT we didn't anticipate.
        try {
            $terminal->delete();
        } catch (QueryException) {
            return redirect()->route('terminals.index')->with('error',
                "\u{201C}{$terminal->code}\u{201D} is still referenced and can't be deleted. Set it to Inactive instead.");
        }

        return redirect()->route('terminals.index')
            ->with('status', "Terminal \u{201C}{$terminal->code}\u{201D} was deleted.");
    }

    /**
     * routes-referencing-terminal counts keyed by terminal id. A route can name the
     * same terminal only once (origin != destination is enforced), so summing the
     * two per-column counts gives the true number of routes touching each terminal.
     *
     * @param  array<int, string>  $terminalIds
     * @return Collection<string, int>
     */
    private function routeUsageCounts(array $terminalIds): Collection
    {
        if ($terminalIds === []) {
            return collect();
        }

        $origins = DB::table('routes')
            ->select('origin_terminal_id as tid', DB::raw('count(*) as c'))
            ->whereIn('origin_terminal_id', $terminalIds)
            ->groupBy('origin_terminal_id')
            ->pluck('c', 'tid');

        $dests = DB::table('routes')
            ->select('destination_terminal_id as tid', DB::raw('count(*) as c'))
            ->whereIn('destination_terminal_id', $terminalIds)
            ->groupBy('destination_terminal_id')
            ->pluck('c', 'tid');

        return collect($terminalIds)->mapWithKeys(fn ($id) => [
            $id => (int) ($origins[$id] ?? 0) + (int) ($dests[$id] ?? 0),
        ]);
    }

    private function organizationsFor(AdminContext $ctx): Collection
    {
        return $ctx->isSuperAdmin()
            ? Organization::orderBy('name')->get(['id', 'name'])
            : collect();
    }
}
