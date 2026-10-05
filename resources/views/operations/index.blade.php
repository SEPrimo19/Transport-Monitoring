@extends('layouts.app')

@section('title', 'Live Operations')
@section('heading', 'Live Operations')
@section('nav_operations', 'active')

@push('head')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css">
    <style>
        #ops-map { height: 70vh; min-height: 460px; width: 100%; border-radius: .5rem; }
        #ops-list { height: 70vh; min-height: 460px; overflow-y: auto; }
        .ops-dot { width: .7rem; height: .7rem; border-radius: 50%; display: inline-block; flex: none; }
        .min-w-0 { min-width: 0; }
        .ops-veh { background: transparent; border: 0; }
    </style>
@endpush

@section('content')
    <div class="page-toolbar d-flex justify-content-between align-items-center flex-wrap gap-2">
        <p class="text-muted mb-0">
            <strong id="ops-count">{{ count($vehicles) }}</strong> {{ Str::plural('vehicle', count($vehicles)) }} transmitting
            {{ $ctx->isSuperAdmin() ? 'across all organizations' : 'in your organization' }}
        </p>
        <span class="text-muted small">
            <i class="bi bi-arrow-repeat me-1"></i>Auto-refreshing · last update <span id="ops-updated">—</span>
        </span>
    </div>

    @if (count($corridors) > 1)
        {{-- Corridor filter. Built from the org's active routes, so it fits any
             cooperative rather than assuming AMOEC's two corridors. --}}
        <div class="d-flex flex-wrap gap-2 mb-3" id="ops-filter">
            <button type="button" class="btn btn-sm btn-brand ops-chip active" data-code="">All routes</button>
            @foreach ($corridors as $c)
                <button type="button" class="btn btn-sm btn-outline-secondary ops-chip"
                        data-code="{{ $c['code'] }}"
                        @if ($c['bounds']) data-bounds="{{ json_encode($c['bounds']) }}" @endif
                        title="{{ $c['name'] }}">{{ $c['name'] }}</button>
            @endforeach
        </div>
    @endif

    <div class="d-flex flex-wrap gap-3 small mb-3">
        <span><span class="ops-dot me-1" style="background:#198754"></span>Live (≤30s)</span>
        <span><span class="ops-dot me-1" style="background:#20c997"></span>Recent (≤2m)</span>
        <span><span class="ops-dot me-1" style="background:#ffc107"></span>Delayed (≤5m)</span>
        <span><span class="ops-dot me-1" style="background:#fd7e14"></span>Stale (≤15m)</span>
        <span><span class="ops-dot me-1" style="background:#6c757d"></span>Offline (&gt;15m)</span>
    </div>

    <div class="row g-3">
        <div class="col-12 col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-2">
                    <div id="ops-map"></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-4">
            <div class="card border-0 shadow-sm">
                <div id="ops-list">
                    <div id="ops-list-body"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const DATA_URL = @json(route('operations.data'));
            const DEFAULT_CENTER = @json($defaultCenter);
            const INITIAL = @json($vehicles);

            const map = L.map('ops-map').setView(DEFAULT_CENTER, 9);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19, attribution: '&copy; OpenStreetMap contributors'
            }).addTo(map);

            const markers = {};
            let fittedOnce = false;
            let activeRoute = '';   // '' = every route
            let latest = INITIAL;   // last payload, so a chip can re-render without waiting for a poll

            function esc(s) {
                return (s == null ? '' : String(s)).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
            }

            // Directional arrow (rotated by heading) like the mobile app; a plain dot
            // when there's no heading. Coloured by freshness.
            function vehicleIcon(v) {
                const size = 40;
                let html;
                if (v.heading == null) {
                    html = '<span style="display:block;width:18px;height:18px;margin:' + ((size - 18) / 2) + 'px;' +
                        'border-radius:50%;background:' + v.color + ';border:2.5px solid #fff;box-shadow:0 0 3px rgba(0,0,0,.6)"></span>';
                } else {
                    html = '<svg width="' + size + '" height="' + size + '" viewBox="0 0 24 24" ' +
                        'style="transform:rotate(' + v.heading + 'deg);transform-origin:50% 50%;filter:drop-shadow(0 1px 2px rgba(0,0,0,.55))">' +
                        '<path d="M12 1.5 L19 21.5 L12 16.5 L5 21.5 Z" fill="' + v.color + '" stroke="#fff" stroke-width="1.6"/></svg>';
                }
                return L.divIcon({ className: 'ops-veh', iconSize: [size, size], iconAnchor: [size / 2, size / 2], html: html });
            }

            function popupHtml(v) {
                return '<strong>' + esc(v.plate || '—') + '</strong>' + (v.fleet_code ? ' · ' + esc(v.fleet_code) : '') +
                    '<br>Route: ' + esc(v.route || '—') +
                    '<br>Driver: ' + esc(v.driver || '—') +
                    (v.speed_kmh != null ? '<br>Speed: ' + v.speed_kmh + ' km/h' : '') +
                    (v.org ? '<br>Org: ' + esc(v.org) : '') +
                    '<br><span style="color:' + v.color + '">●</span> ' + esc(v.level) + ' · ' + esc(v.age_label);
            }

            function listItem(v) {
                return '<div class="d-flex align-items-center gap-2 px-3 py-2 border-bottom">' +
                    '<span class="ops-dot" style="background:' + v.color + '"></span>' +
                    '<div class="flex-grow-1 min-w-0">' +
                        '<div class="fw-semibold text-truncate">' + esc(v.plate || '—') +
                            ' <span class="text-muted small">' + esc(v.route || '') + '</span></div>' +
                        '<div class="small text-muted text-truncate">' + esc(v.driver || '—') + ' · ' +
                            esc(v.level) + ' · ' + esc(v.age_label) +
                            (v.speed_kmh != null ? ' · ' + v.speed_kmh + ' km/h' : '') + '</div>' +
                    '</div></div>';
            }

            function render(vehicles) {
                latest = vehicles;
                // The filter narrows what is drawn and listed; polling still collects
                // every vehicle, so clearing the filter needs no round trip.
                const shown = activeRoute ? vehicles.filter(v => v.route === activeRoute) : vehicles;

                document.getElementById('ops-count').textContent = shown.length;

                const listBody = document.getElementById('ops-list-body');
                listBody.innerHTML = shown.length ? '' :
                    '<div class="text-muted text-center p-4">' +
                    (activeRoute ? 'No vehicles on this route right now.'
                                 : 'No vehicles are transmitting right now.') +
                    '</div>';

                const seen = new Set();
                const bounds = [];
                shown.forEach(function (v) {
                    seen.add(v.vehicle_id);
                    bounds.push([v.lat, v.lng]);

                    let m = markers[v.vehicle_id];
                    if (!m) {
                        m = L.marker([v.lat, v.lng], { icon: vehicleIcon(v) }).addTo(map);
                        markers[v.vehicle_id] = m;
                    } else {
                        m.setLatLng([v.lat, v.lng]);
                        m.setIcon(vehicleIcon(v));
                    }
                    m.bindPopup(popupHtml(v));

                    listBody.insertAdjacentHTML('beforeend', listItem(v));
                });

                Object.keys(markers).forEach(function (id) {
                    if (!seen.has(id)) { map.removeLayer(markers[id]); delete markers[id]; }
                });

                if (!fittedOnce && bounds.length) {
                    map.fitBounds(bounds, { padding: [40, 40], maxZoom: 14 });
                    fittedOnce = true;
                }
                document.getElementById('ops-updated').textContent = new Date().toLocaleTimeString();
            }

            const filterBar = document.getElementById('ops-filter');
            if (filterBar) {
                filterBar.addEventListener('click', function (e) {
                    const chip = e.target.closest('.ops-chip');
                    if (!chip) return;

                    activeRoute = chip.dataset.code || '';
                    filterBar.querySelectorAll('.ops-chip').forEach(function (b) {
                        const on = b === chip;
                        b.classList.toggle('active', on);
                        b.classList.toggle('btn-brand', on);
                        b.classList.toggle('btn-outline-secondary', !on);
                    });

                    fittedOnce = false;          // let the new selection reframe the map
                    render(latest);

                    // Vehicles win the viewport. Only when the route has nothing running
                    // does the map fall back to the corridor's own extent, so choosing a
                    // quiet route still shows where it is instead of leaving the map put.
                    const shown = activeRoute ? latest.filter(v => v.route === activeRoute) : latest;
                    if (!shown.length && chip.dataset.bounds) {
                        map.fitBounds(JSON.parse(chip.dataset.bounds), { padding: [40, 40], maxZoom: 14 });
                        fittedOnce = true;
                    }
                });
            }

            render(INITIAL);

            async function poll() {
                try {
                    const r = await fetch(DATA_URL, { headers: { 'Accept': 'application/json' } });
                    if (r.ok) {
                        const d = await r.json();
                        render(d.vehicles || []);
                    }
                } catch (e) { /* keep the last good render */ }
            }
            setInterval(poll, 12000);
            setTimeout(function () { map.invalidateSize(); }, 200);
        });
    </script>
@endpush
