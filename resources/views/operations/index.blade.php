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
        {{-- A map full of markers looks identical whether it is live or frozen, so a
             failed poll has to say so. Without this the only clue was the timestamp
             above quietly ceasing to advance. --}}
        <div id="ops-stale" class="alert alert-warning py-2 px-3 mt-2 mb-0 d-none" role="status">
            <i class="bi bi-exclamation-triangle me-1"></i><span id="ops-stale-text"></span>
        </div>
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
        {{-- Same five the passenger sees; mirrors VehicleFreshnessPalette and
             OperationsController::classify. Change all three together. --}}
        <span><span class="ops-dot me-1" style="background:#15A04A"></span>Live (≤30s)</span>
        <span><span class="ops-dot me-1" style="background:#6FBF5E"></span>Recent (≤2m)</span>
        <span><span class="ops-dot me-1" style="background:#E89C12"></span>Delayed (≤5m)</span>
        <span><span class="ops-dot me-1" style="background:#E0512B"></span>Stale (≤15m)</span>
        <span><span class="ops-dot me-1" style="background:#7E8A97"></span>Offline (&gt;15m)</span>
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

            // The same bus the passengers see.
            //
            // This drew a rotated arrow, which is what the mobile app drew until a
            // tester asked for a bus. Geometry below mirrors VehicleMarkerFactory in
            // the Android repo: a 54-unit square, a body 0.62 long by 0.30 wide, wing
            // mirrors at 0.62 of the half-length, a pale roof panel and the operator's
            // colour as a stripe across it. Keep the two in step -- a dispatcher and a
            // passenger discussing one bus should be looking at the same object.
            //
            // The body carries freshness (v.color) and the stripe carries the brand,
            // for the same reason as on mobile: freshness changes minute to minute and
            // is what someone actually needs, a brand never changes.
            function darken(hex, factor) {
                const m = /^#?([0-9a-f]{6})$/i.exec(hex || '');
                if (!m) return '#333';
                const n = parseInt(m[1], 16);
                const c = [(n >> 16) & 255, (n >> 8) & 255, n & 255]
                    .map(x => Math.max(0, Math.min(255, Math.round(x * factor))));
                return 'rgb(' + c.join(',') + ')';
            }

            function vehicleIcon(v) {
                const size = 54;
                const c = 27, hl = 16.74, hw = 8.1;      // centre, half-length, half-width
                const edge = darken(v.color, 0.55);
                // No heading means no bearing to show, so the silhouette simply points
                // up rather than inventing a direction.
                const rotate = v.heading == null ? 0 : v.heading;

                const html =
                    '<svg width="' + size + '" height="' + size + '" viewBox="0 0 54 54" ' +
                      'style="transform:rotate(' + rotate + 'deg);transform-origin:50% 50%;' +
                      'filter:drop-shadow(0 1px 2px rgba(0,0,0,.45))">' +
                      // mirrors, under the body so they read as attached to it
                      '<rect x="15.93" y="15.81" width="2.97" height="1.62" rx=".81" fill="' + edge + '"/>' +
                      '<rect x="35.1"  y="15.81" width="2.97" height="1.62" rx=".81" fill="' + edge + '"/>' +
                      // body
                      '<rect x="18.9" y="10.26" width="16.2" height="33.48" rx="4.46" ' +
                        'fill="' + v.color + '" stroke="' + edge + '" stroke-width="1.1"/>' +
                      // windscreen (wider) and rear window -- the asymmetry is the nose
                      '<rect x="20.68" y="12.60" width="12.64" height="5.03" rx="1.2" fill="#1C2B33" opacity=".92"/>' +
                      '<rect x="21.65" y="37.04" width="10.70" height="4.36" rx="1.2" fill="#1C2B33" opacity=".92"/>' +
                      // roof panel, then the operator stripe across it
                      '<rect x="21.01" y="20.30" width="11.98" height="14.07" rx="1.1" fill="#F2F4F5" opacity=".95"/>' +
                      '<rect x="21.01" y="26.36" width="11.98" height="1.94" fill="var(--amoec-brand, #1B5E20)"/>' +
                    '</svg>';

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

            var staleBox = document.getElementById('ops-stale');
            var staleText = document.getElementById('ops-stale-text');
            var failures = 0;
            var timer = null;

            // One transient failure on a provincial connection is normal and must not
            // cry wolf; two in a row means twenty-four seconds of nothing, which is
            // worth saying. A 401 is reported immediately -- waiting cannot fix an
            // expired session, and the operator needs to know to sign in again rather
            // than keep watching markers that will never move.
            function reportFailure(message, immediate) {
                failures += 1;
                if (!immediate && failures < 2) return;
                staleText.textContent = message;
                staleBox.classList.remove('d-none');
            }

            function reportRecovered() {
                failures = 0;
                staleBox.classList.add('d-none');
            }

            async function poll() {
                try {
                    const r = await fetch(DATA_URL, { headers: { 'Accept': 'application/json' } });
                    if (r.status === 401 || r.status === 419) {
                        reportFailure('Your session has expired. Sign in again to resume live tracking.', true);
                        stop();
                        return;
                    }
                    if (!r.ok) {
                        reportFailure('Live data is not updating (server returned ' + r.status + '). Showing the last known positions.', false);
                        return;
                    }
                    const d = await r.json();
                    render(d.vehicles || []);
                    reportRecovered();
                } catch (e) {
                    // Network down, or a response that was not the JSON we expected.
                    reportFailure('Live data is not updating. Showing the last known positions.', false);
                }
            }

            function start() {
                if (timer === null) timer = setInterval(poll, 12000);
            }

            function stop() {
                if (timer !== null) { clearInterval(timer); timer = null; }
            }

            // A hidden tab polls nobody. Left open overnight this ran five times a
            // minute against a five-table join for no viewer. Same pattern the
            // notification bell already uses.
            document.addEventListener('visibilitychange', function () {
                if (document.visibilityState === 'visible') { poll(); start(); } else { stop(); }
            });

            if (document.visibilityState === 'visible') start();
            setTimeout(function () { map.invalidateSize(); }, 200);
        });
    </script>
@endpush
