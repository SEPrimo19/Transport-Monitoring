<?php

namespace App\Http\Controllers;

use App\Models\AccountAdminEvent;
use App\Models\Profile;
use App\Support\AdminContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Read-only view of public.account_admin_events: who created, reset or removed a
 * driver or cooperative-administrator login, and when.
 *
 * The data has been written since migration 037 and nothing displayed it, so the
 * accountability record existed but was only reachable by SQL. Migration 037's own
 * header names the case it exists for: a passenger files a misconduct report naming a
 * driver, and without this nobody can tell whether that driver's login was created,
 * reset or removed around the same time, or by whom.
 *
 * WHO SEES WHAT — not the usual tenant rule, see {@see AccountAdminEvent}:
 *
 *   org_admin    their own cooperative's account administration, including actions a
 *                super_admin took on it. The co-op is who the accountability is owed to.
 *   super_admin  platform-tier events only (organization_id IS NULL). Appointing
 *                administrators is theirs; what happens inside a cooperative is not.
 *
 * Hence no tenant.admin / super.admin gate on the route: both tiers may open it, and
 * the scope differs rather than the access. The enforcement is scopeVisibleTo, because
 * the console connects as the database owner and bypasses the RLS policy in 037.
 *
 * Nothing here can write. The table is append-only by design and the only console code
 * that appends is OrgAdminController's own audit insert.
 */
class AdminActivityController extends Controller
{
    private const PER_PAGE = 25;

    public function index(Request $request, AdminContext $ctx): View
    {
        $action = $request->query('action');
        if (! is_string($action) || ! array_key_exists($action, AccountAdminEvent::ACTIONS)) {
            $action = null;
        }

        $kind = $request->query('kind');
        if (! is_string($kind) || ! array_key_exists($kind, AccountAdminEvent::KINDS)) {
            $kind = null;
        }

        $events = AccountAdminEvent::query()
            ->visibleTo($ctx)
            ->when($action, fn ($q) => $q->where('action', $action))
            ->when($kind, fn ($q) => $q->where('target_kind', $kind))
            ->orderByDesc('occurred_at')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        // Counts for the filter chips, under the SAME visibility scope — a chip that
        // counted rows the viewer cannot open would be a disclosure in itself.
        $actionCounts = AccountAdminEvent::query()
            ->visibleTo($ctx)
            ->when($kind, fn ($q) => $q->where('target_kind', $kind))
            ->selectRaw('action, count(*) as n')->groupBy('action')->pluck('n', 'action');

        return view('activity.index', [
            'events' => $events,
            'actorNames' => $this->actorNames($events->pluck('actor_profile_id')),
            'activeAction' => $action,
            'activeKind' => $kind,
            'actionCounts' => $actionCounts,
            'isPlatformView' => $ctx->isSuperAdmin(),
        ]);
    }

    /**
     * Display names for the administrators who acted, for this page only.
     *
     * A direct Profile read, which is normally something to avoid because Profile
     * carries no organization scope. It is bounded here by construction: the ids come
     * from rows the viewer has already been granted, so this resolves names for events
     * they can read and cannot enumerate anyone else. actor_profile_id is also
     * ON DELETE SET NULL, so a removed administrator simply has no name to show and the
     * event keeps its actor_role.
     *
     * @param  \Illuminate\Support\Collection<int, string|null>  $ids
     * @return \Illuminate\Support\Collection<string, string>
     */
    private function actorNames(\Illuminate\Support\Collection $ids): \Illuminate\Support\Collection
    {
        $ids = $ids->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return Profile::whereIn('id', $ids->all())->pluck('full_name', 'id');
    }
}
