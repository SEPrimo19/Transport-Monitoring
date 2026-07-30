@extends('layouts.app')

@section('title', 'Trip')
@section('heading', 'Trip detail')
@section('nav_trips', 'active')

@php
    $dur = $trip->durationMinutes();
    $durLabel = $dur === null ? '—' : ($dur < 60 ? $dur . ' min' : intdiv($dur, 60) . 'h ' . ($dur % 60) . 'm');
@endphp

@push('head')
    @if (count($trace) >= 2)
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css">
        <style>#trip-map { height: 52vh; min-height: 360px; width: 100%; border-radius: .5rem; }</style>
    @endif
@endpush

@section('content')
    <div class="page-toolbar d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
            <span class="badge {{ $trip->statusBadge() }} fs-6">{{ $trip->statusLabel() }}</span>
            <span class="ms-1">{{ $trip->route?->code ?? '—' }} — {{ $trip->route?->name ?? '' }}</span>
        </div>
        <a href="{{ route('trips.index') }}" class="btn btn-sm btn-light">
            <i class="bi bi-arrow-left me-1"></i>Back to trips
        </a>
    </div>

    <div class="row g-3">
        <div class="col-12 col-lg-8">
            @if (count($trace) >= 2)
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body p-2"><div id="trip-map"></div></div>
                </div>
            @else
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body text-muted text-center py-5">
                        <i class="bi bi-geo-alt fs-3 d-block mb-2"></i>
                        No GPS trace was recorded for this trip.
                    </div>
                </div>
            @endif
        </div>

        <div class="col-12 col-lg-4">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <h2 class="h6 mb-3">Details</h2>
                    <dl class="row mb-0 small">
                        <dt class="col-5">Vehicle</dt>
                        <dd class="col-7">{{ $trip->vehicle?->plate_number ?? '—' }}</dd>
                        <dt class="col-5">Driver</dt>
                        <dd class="col-7">{{ $trip->driver?->profile?->full_name ?? '—' }}</dd>
                        <dt class="col-5">Started</dt>
                        <dd class="col-7">{{ optional($trip->started_at)->format('M j, Y g:i A') ?? '—' }}</dd>
                        <dt class="col-5">Ended</dt>
                        <dd class="col-7">{{ optional($trip->ended_at)->format('M j, Y g:i A') ?? '—' }}</dd>
                        <dt class="col-5">Duration</dt>
                        <dd class="col-7">{{ $durLabel }}</dd>
                        <dt class="col-5">GPS points</dt>
                        <dd class="col-7">{{ number_format($rawPointCount) }}</dd>
                    </dl>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h2 class="h6 mb-3">Occupancy</h2>
                    @forelse ($occupancy as $o)
                        <div class="d-flex justify-content-between border-bottom py-1 small">
                            <span class="badge text-bg-light border">{{ ucfirst(str_replace('_', ' ', $o->occupancy)) }}</span>
                            <span class="text-muted">{{ \Illuminate\Support\Carbon::parse($o->reported_at)->format('g:i A') }}</span>
                        </div>
                    @empty
                        <p class="text-muted small mb-0">No occupancy updates were recorded.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection

@if (count($trace) >= 2)
    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const TRACE = @json($trace);
                const map = L.map('trip-map');
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19, attribution: '&copy; OpenStreetMap contributors'
                }).addTo(map);
                const line = L.polyline(TRACE, { color: '#0d6efd', weight: 4 }).addTo(map);
                L.circleMarker(TRACE[0], { radius: 7, color: '#198754', fillColor: '#198754', fillOpacity: 0.9 })
                    .addTo(map).bindTooltip('Start');
                L.circleMarker(TRACE[TRACE.length - 1], { radius: 7, color: '#dc3545', fillColor: '#dc3545', fillOpacity: 0.9 })
                    .addTo(map).bindTooltip('End');
                map.fitBounds(line.getBounds(), { padding: [30, 30] });
                setTimeout(function () { map.invalidateSize(); }, 200);
            });
        </script>
    @endpush
@endif
