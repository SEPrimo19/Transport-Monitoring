@extends('layouts.app')

@section('title', 'Admin activity')
@section('heading', 'Admin activity')
@section('nav_activity', 'active')

@section('content')
    <div class="page-toolbar d-flex justify-content-between align-items-center flex-wrap gap-2">
        <p class="text-muted mb-0">
            @if ($isPlatformView)
                Platform-tier account administration. Actions taken inside a cooperative
                are visible to that cooperative, not here.
            @else
                Who created, reset or removed a login in {{ $orgName ?? 'your organization' }} — including
                actions taken by the platform administrator.
            @endif
        </p>
        <span class="badge text-bg-light border">
            <i class="bi bi-shield-lock me-1"></i>Read-only
        </span>
    </div>

    {{-- Filters. Server-side, like Trips: this list paginates, so filtering the rows on
         screen would quietly misreport the rest. --}}
    <div class="d-flex flex-wrap gap-2 mb-3">
        <a href="{{ route('activity.index', array_filter(['kind' => $activeKind])) }}"
           class="btn btn-sm {{ $activeAction === null ? 'btn-primary' : 'btn-outline-secondary' }}">
            All <span class="badge text-bg-light ms-1">{{ $actionCounts->sum() }}</span>
        </a>
        @foreach (\App\Models\AccountAdminEvent::ACTIONS as $value => $label)
            @if (($actionCounts[$value] ?? 0) > 0 || $activeAction === $value)
                <a href="{{ route('activity.index', array_filter(['action' => $value, 'kind' => $activeKind])) }}"
                   class="btn btn-sm {{ $activeAction === $value ? 'btn-primary' : 'btn-outline-secondary' }}">
                    {{ $label }} <span class="badge text-bg-light ms-1">{{ $actionCounts[$value] ?? 0 }}</span>
                </a>
            @endif
        @endforeach

        <span class="vr mx-1 d-none d-sm-inline"></span>

        <a href="{{ route('activity.index', array_filter(['action' => $activeAction])) }}"
           class="btn btn-sm {{ $activeKind === null ? 'btn-secondary' : 'btn-outline-secondary' }}">Everyone</a>
        @foreach (\App\Models\AccountAdminEvent::KINDS as $value => $label)
            <a href="{{ route('activity.index', array_filter(['kind' => $value, 'action' => $activeAction])) }}"
               class="btn btn-sm {{ $activeKind === $value ? 'btn-secondary' : 'btn-outline-secondary' }}">{{ $label }}s</a>
        @endforeach
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>When</th>
                        <th>Action</th>
                        <th>Account</th>
                        <th>By</th>
                        <th>Result</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($events as $event)
                        <tr>
                            <td class="text-nowrap">
                                {{ optional($event->occurred_at)->format('d M Y, H:i') ?? '—' }}
                            </td>
                            <td>
                                <span class="badge {{ $event->actionBadge() }}">{{ $event->actionLabel() }}</span>
                            </td>
                            <td class="min-w-0">
                                {{-- target_identifier is denormalised precisely so a removed
                                     account still shows who the event was about. --}}
                                <div class="fw-semibold text-truncate">{{ $event->target_identifier ?? '—' }}</div>
                                <div class="small text-muted">{{ $event->kindLabel() }}</div>
                            </td>
                            <td class="min-w-0">
                                <div class="text-truncate">
                                    {{ $actorNames[$event->actor_profile_id] ?? 'Removed account' }}
                                </div>
                                <div class="small text-muted">{{ $event->actor_role }}</div>
                            </td>
                            <td>
                                @if ($event->succeeded)
                                    <span class="text-success"><i class="bi bi-check-circle me-1"></i>Succeeded</span>
                                @else
                                    <span class="text-danger"><i class="bi bi-x-circle me-1"></i>Failed</span>
                                @endif
                                @if ($event->detail)
                                    <div class="small text-muted">{{ $event->detail }}</div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-5">
                                <i class="bi bi-clipboard-check d-block mb-3" style="font-size:1.75rem;opacity:.45"></i>
                                @if ($activeAction || $activeKind)
                                    No account activity matches this filter.
                                @elseif ($isPlatformView)
                                    <p class="mb-1">No platform-tier account activity yet.</p>
                                    <p class="small mb-0">
                                        Creating or removing a cooperative's administrator is recorded
                                        against <em>that cooperative</em>, so it appears in their console
                                        rather than this one.
                                    </p>
                                @else
                                    <p class="mb-1">No account activity yet.</p>
                                    <p class="small mb-0">
                                        Creating a driver login, resetting a password or removing access
                                        will be recorded here.
                                    </p>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($events->hasPages())
        <div class="mt-3">{{ $events->links() }}</div>
    @endif

    <p class="text-muted small mt-3 mb-0">
        <i class="bi bi-info-circle me-1"></i>
        Append-only. Entries cannot be edited or removed from this console, and no password
        or password hash is ever recorded.
    </p>
@endsection
