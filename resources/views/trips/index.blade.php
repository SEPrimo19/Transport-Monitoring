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
    <div class="d-flex flex-wrap gap-2 mb-3">
        <a href="{{ route('trips.index') }}"
           class="btn btn-sm {{ $activeStatus === null ? 'btn-primary' : 'btn-outline-secondary' }}">
            All <span class="badge text-bg-light ms-1">{{ $counts->sum() }}</span>
        </a>
        @foreach (\App\Models\Trip::STATUSES as $value => $label)
            @if (($counts[$value] ?? 0) > 0 || $activeStatus === $value)
                <a href="{{ route('trips.index', ['status' => $value]) }}"
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
                                    <td colspan="8" class="text-center text-muted py-4">No trips recorded yet.</td>
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
