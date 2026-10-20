@extends('layouts.app')

@section('title', 'Trips')
@section('heading', 'Trip history')
@section('nav_trips', 'active')

@php
    $fmtDur = function (?int $m) {
        if ($m === null) return '—';
        if ($m < 60) return $m . ' min';
        return intdiv($m, 60) . 'h ' . ($m % 60) . 'm';
    };
@endphp

@section('content')
    {{-- Status filter chips --}}
    <div class="page-toolbar d-flex flex-wrap gap-2">
        <a href="{{ route('trips.index', $searchTerm !== null ? ['q' => $searchTerm] : []) }}"
           class="btn btn-sm {{ $activeStatus === null ? 'btn-primary' : 'btn-outline-secondary' }}">
            All <span class="badge text-bg-light ms-1">{{ $counts->sum() }}</span>
        </a>
        @foreach (\App\Models\Trip::STATUSES as $value => $label)
            @if (($counts[$value] ?? 0) > 0 || $activeStatus === $value)
                <a href="{{ route('trips.index', array_filter(['status' => $value, 'q' => $searchTerm], fn ($v) => $v !== null)) }}"
                   class="btn btn-sm {{ $activeStatus === $value ? 'btn-primary' : 'btn-outline-secondary' }}">
                    {{ $label }} <span class="badge text-bg-light ms-1">{{ $counts[$value] ?? 0 }}</span>
                </a>
            @endif
        @endforeach
    </div>

    <div class="row g-3">
        {{-- Trip list --}}
        <div class="col-12 col-lg-8">
            <div class="card border-0 shadow-sm">
                {{-- Trips is the only index that paginates, so unlike the other screens
                     this searches in the DATABASE. Filtering the twenty rows on this page
                     client-side would report "no matches" for a plate sitting on page
                     three -- confidently, and wrongly. It still needs no Enter: the form
                     submits itself once typing pauses. --}}
                <form method="GET" action="{{ route('trips.index') }}"
                      class="p-3 border-bottom table-search" id="trip-search-form">
                    @if ($activeStatus)
                        <input type="hidden" name="status" value="{{ $activeStatus }}">
                    @endif
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white border-end-0 text-muted">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="search" name="q" id="trip-search" value="{{ $searchTerm }}"
                               class="form-control border-start-0 ps-0"
                               placeholder="Search plate, body number, driver, route…"
                               aria-label="Search all trips" autocomplete="off">
                        @if ($searchTerm !== null)
                            <a class="btn btn-outline-secondary" aria-label="Clear search"
                               href="{{ route('trips.index', $activeStatus ? ['status' => $activeStatus] : []) }}">
                                <i class="bi bi-x-lg"></i>
                            </a>
                        @endif
                    </div>
                    @if ($searchTerm !== null)
                        <div class="small text-muted mt-2" role="status">
                            {{ $trips->total() }} {{ Str::plural('trip', $trips->total()) }}
                            matching “{{ $searchTerm }}”@if ($activeStatus) in {{ \App\Models\Trip::STATUSES[$activeStatus] }}@endif.
                        </div>
                    @endif
                </form>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Route</th>
                                <th>Vehicle</th>
                                <th>Driver</th>
                                <th>Status</th>
                                <th class="text-end">Duration</th>
                                <th class="text-end">Points</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($trips as $trip)
                                <tr>
                                    <td class="small text-muted">
                                        {{ optional($trip->started_at ?? $trip->created_at)->format('M j, Y') }}<br>
                                        {{ optional($trip->started_at)->format('g:i A') }}
                                    </td>
                                    <td>{{ $trip->route?->code ?? '—' }}</td>
                                    <td>{{ $trip->vehicle?->plate_number ?? '—' }}</td>
                                    <td class="small">{{ $trip->driver?->profile?->full_name ?? '—' }}</td>
                                    <td><span class="badge {{ $trip->statusBadge() }}">{{ $trip->statusLabel() }}</span></td>
                                    <td class="text-end">{{ $fmtDur($trip->durationMinutes()) }}</td>
                                    <td class="text-end">
                                        @php $pc = (int) ($pointCounts[$trip->id] ?? 0); @endphp
                                        @if ($pc > 0)
                                            <span class="badge text-bg-info">{{ number_format($pc) }}</span>
                                        @else
                                            <span class="text-muted">0</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('trips.show', $trip) }}" class="btn btn-sm btn-outline-secondary">View</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="p-0">
                                        <div class="empty-state text-center">
                                            <i class="bi bi-clock-history"></i>
                                            <p class="empty-state-title mb-0">No trips recorded yet</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if ($trips->hasPages())
                <div class="mt-3">{{ $trips->links('pagination::bootstrap-5') }}</div>
            @endif
        </div>

        {{-- Per-route rollup --}}
        <div class="col-12 col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h2 class="h6 mb-3">By route{{ $activeStatus ? ' · ' . \App\Models\Trip::STATUSES[$activeStatus] : '' }}</h2>
                    @forelse ($routeStats as $rs)
                        <div class="d-flex justify-content-between align-items-center border-bottom py-2 small">
                            <span class="fw-semibold">{{ $routeNames[$rs->route_id] ?? '—' }}</span>
                            <span class="text-muted text-end">
                                {{ $rs->trips }} {{ Str::plural('trip', $rs->trips) }}
                                · avg {{ $fmtDur((int) round($rs->avg_secs / 60)) }}
                            </span>
                        </div>
                    @empty
                        <p class="text-muted small mb-0">No completed trips to summarize.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    (function () {
        var form = document.getElementById('trip-search-form');
        var input = document.getElementById('trip-search');
        if (!form || !input) return;

        // Submitting reloads the page, which would drop the caret at the start of the
        // field and make the next keystroke land in the wrong place. Put it back.
        var FOCUS_KEY = 'amoec.tripSearchFocus';
        try {
            if (sessionStorage.getItem(FOCUS_KEY) === '1') {
                sessionStorage.removeItem(FOCUS_KEY);
                input.focus();
                input.setSelectionRange(input.value.length, input.value.length);
            }
        } catch (e) {}

        function submitAndKeepFocus() {
            // Flag it so the reload knows the search caused it. Paging or a status chip
            // reloads the same page with a value still in the box, and pulling focus and
            // scroll back up there would fight the click the admin just made.
            try { sessionStorage.setItem(FOCUS_KEY, '1'); } catch (e) {}
            form.submit();
        }

        var timer = null;
        input.addEventListener('input', function () {
            // Long enough that a word is typed before the round trip, short enough to
            // still feel like it is keeping up. Every keystroke restarts it, so typing
            // "ABC-123" costs one query, not seven.
            clearTimeout(timer);
            timer = setTimeout(submitAndKeepFocus, 450);
        });
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { clearTimeout(timer); }        // let it submit now
            if (e.key === 'Escape' && input.value !== '') {
                clearTimeout(timer);
                input.value = '';
                submitAndKeepFocus();
            }
        });
    })();
</script>
@endpush
