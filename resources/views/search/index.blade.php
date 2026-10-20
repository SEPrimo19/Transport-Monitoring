@extends('layouts.app')

@section('title', $term ? 'Search · '.$term : 'Search')
@section('heading', 'Search')
@section('nav_dashboard', 'active')

@section('content')
    {{-- The box is repeated here, pre-filled, so a near-miss can be corrected without
         going back to the dashboard first. Submit-on-enter, like the dashboard: this
         one reads eight tables, so it runs when you ask it to, not per keystroke. --}}
    <form method="GET" action="{{ route('search') }}" class="mb-4" role="search">
        <div class="input-group">
            <span class="input-group-text bg-white border-end-0 text-muted">
                <i class="bi bi-search"></i>
            </span>
            <input type="search" name="q" value="{{ $term }}" autofocus
                   class="form-control form-control-lg border-start-0 ps-0"
                   placeholder="Search vehicles, drivers, terminals, routes, schedules, assignments, trips, reports…"
                   aria-label="Search everything" autocomplete="off">
            <button class="btn btn-brand px-4" type="submit">Search</button>
        </div>
    </form>

    @if ($term === null)
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center text-muted py-5">
                <i class="bi bi-search d-block mb-3" style="font-size:2rem;opacity:.4"></i>
                Type a plate, a name, a route code or a terminal and press Enter.
            </div>
        </div>
    @elseif (empty($groups))
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <i class="bi bi-slash-circle d-block mb-3 text-muted" style="font-size:2rem;opacity:.5"></i>
                <p class="mb-1">No matches for <strong>“{{ $term }}”</strong>.</p>
                <p class="text-muted small mb-0">
                    Searched vehicles, drivers, terminals, routes, schedules, assignments,
                    trips and reports in {{ $orgName ?? 'your organization' }}.
                </p>
            </div>
        </div>
    @else
        @php $found = collect($groups)->sum('total'); @endphp
        <p class="text-muted mb-3">
            {{ $found }} {{ Str::plural('match', $found) }} for <strong>“{{ $term }}”</strong>
            across {{ count($groups) }} {{ Str::plural('section', count($groups)) }}.
        </p>

        <div class="row g-3">
            @foreach ($groups as $group)
                <div class="col-12 col-xl-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body pb-2 d-flex justify-content-between align-items-center">
                            <h2 class="h6 mb-0">
                                <i class="bi {{ $group['icon'] }} me-2 text-muted"></i>{{ $group['label'] }}
                                <span class="badge text-bg-light border ms-1">{{ $group['total'] }}</span>
                            </h2>
                            <a href="{{ $group['index'] }}" class="small text-decoration-none">Open {{ strtolower($group['label']) }}</a>
                        </div>
                        <div class="list-group list-group-flush">
                            @foreach ($group['rows'] as $row)
                                <a href="{{ $row['url'] }}" class="list-group-item list-group-item-action">
                                    <div class="fw-semibold text-truncate">{{ $row['title'] }}</div>
                                    <div class="small text-muted text-truncate">{{ $row['meta'] }}</div>
                                </a>
                            @endforeach
                        </div>
                        @if ($group['total'] > count($group['rows']))
                            <div class="card-body pt-2">
                                <a href="{{ $group['index'] }}" class="small text-muted text-decoration-none">
                                    {{ $group['total'] - count($group['rows']) }} more —
                                    open {{ strtolower($group['label']) }} and filter there.
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection
