@extends('layouts.app')

@section('title', 'Live Operations')
@section('heading', 'Live Operations')
@section('nav_operations', 'active')

@push('head')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css">
    <style>
        #ops-map { height: 70vh; min-height: 460px; width: 100%; border-radius: .5rem; }
        #ops-list { height: 70vh; min-height: 460px; overflow-y: auto; }
        /* Below lg the two panes stop being side by side and stack, where a 460px
           floor on each means roughly 1000px of content before anything else --
           and a list that scrolls inside a page that also scrolls, which on touch
           is a coin toss over which one moves. So once stacked, the map gives up
           some height and the list grows to its content instead, scrolling only
           when it is genuinely long. */
        @media (max-width: 991.98px) {
            #ops-map { height: 52vh; min-height: 280px; }
            #ops-list { height: auto; min-height: 0; max-height: 65vh; }
        }
        .ops-dot { width: .7rem; height: .7rem; border-radius: 50%; display: inline-block; flex: none; }
        .min-w-0 { min-width: 0; }
        .ops-veh { background: transparent; border: 0; }

        /* ---- Expanded ("theatre") mode -------------------------------
           The stage is pinned over the CONTENT column, not the viewport: --ops-left and
           --ops-top are measured from the live sidebar and page header, so the notification
           bell and the account menu stay reachable and the nav stays where it was. They are
           measured rather than hardcoded because the sidebar has three widths (rail, md, lg)
           and the header wraps on a narrow window.

           Every child below re-anchors onto the map. Nothing is moved in the DOM, so the
           poller keeps writing to the same #ops-list-body and the filter keeps listening on
           the same #ops-filter. */
        #ops-stage.ops-on {
            position: fixed;
            top: var(--ops-top, 0px);
            left: var(--ops-left, 0px);
            right: 0;
            bottom: 0;
            z-index: 1035;          /* over the sticky toolbar (1020), under the offcanvas (1045) */
            background: var(--bs-body-bg, #fff);
            padding: 0;
            overflow: hidden;
        }
        .ops-on .row { height: 100%; margin: 0; }

        /* Map: the whole stage. */
        /* width:auto matters: .col-lg-8 sets width:66.666667%, and on an absolutely
           positioned box an explicit width beats the left:0/right:0 pair, so inset
           alone left the map two thirds wide inside a full-width stage. */
        .ops-on .ops-map-col {
            position: absolute; inset: 0; padding: 0;
            width: auto; max-width: none;
            /* z-index:0 rather than auto, to trap Leaflet. Leaflet numbers its own panes
               400-700 and its controls 800, all inside #ops-map -- but an auto z-index
               creates no stacking context, so those numbers competed directly with the
               floating overlays below and painted straight over them. A stacking context
               here keeps Leaflet's scale private, and the overlays only have to beat 0. */
            z-index: 0;
        }
        .ops-on .ops-map-col .card { height: 100%; border-radius: 0; box-shadow: none !important; }
        .ops-on .ops-map-col .card-body { height: 100%; padding: 0 !important; }
        .ops-on #ops-map { height: 100% !important; min-height: 0; border-radius: 0; }

        /* Vehicle list: a floating panel, top right. */
        .ops-on .ops-list-col {
            position: absolute; top: 4.5rem; right: 1rem; z-index: 2;
            width: 22rem; max-width: calc(100% - 2rem); padding: 0;
        }
        .ops-on .ops-list-col .card {
            background: rgba(255, 255, 255, .94);
            backdrop-filter: blur(6px);
            box-shadow: 0 .5rem 1.5rem rgba(0, 0, 0, .18) !important;
        }
        .ops-on #ops-list-toggle { display: flex !important; background: transparent; }
        .ops-on #ops-list { height: auto; min-height: 0; max-height: calc(100vh - 14rem); }
        .ops-on.ops-list-closed #ops-list { display: none; }

        /* Toolbar + corridor chips: one floating bar across the top. */
        .ops-on .page-toolbar,
        .ops-on #ops-filter {
            position: absolute; left: 1rem; right: 24rem; z-index: 2; margin: 0;
        }
        .ops-on .page-toolbar { top: 1rem; }
        .ops-on #ops-filter { top: 3.4rem; }

        /* Leaflet parks its zoom control in the top-left corner -- exactly where the
           floating toolbar and the corridor chips now sit, so the +/- buttons ended up
           both hidden AND unclickable (elementFromPoint over them returned the toolbar).
           Raising them above the overlays would only swap which one is buried, so the
           corner is pushed down instead, to just below whichever overlay reaches lowest.
           Measured in JS rather than hardcoded: the chips are conditional on the org
           having more than one corridor, and either bar can wrap on a narrow window. */
        .ops-on .leaflet-top.leaflet-left { top: var(--ops-ctrl-top, 6rem); }

        /* Legend: bottom left, clear of Leaflet's attribution on the right. */
        .ops-on .ops-legend {
            position: absolute; left: 1rem; bottom: 1rem; z-index: 2; margin: 0;
        }

        /* One pill treatment for all three floating clusters. */
        .ops-on .page-toolbar,
        .ops-on .ops-legend {
            background: rgba(255, 255, 255, .92);
            backdrop-filter: blur(6px);
            border-radius: .5rem;
            padding: .4rem .75rem;
            box-shadow: 0 .25rem .75rem rgba(0, 0, 0, .12);
        }
        /* The stale-poll warning has to come with us. A map full of markers looks
           identical whether it is live or frozen, and that is twice as true filling a
           projector, so the one place it must never be hidden is here. */
        .ops-on #ops-stale { position: static; width: 100%; }

        @media (max-width: 1199.98px) {
            .ops-on .page-toolbar, .ops-on #ops-filter { right: 1rem; }
            .ops-on .ops-list-col { top: 7rem; }
        }
    </style>
@endpush

@section('content')
{{-- Everything on this page lives inside the stage. In the normal layout the stage is
     an ordinary static div and changes nothing; adding .ops-on pins it over the content
     column and the children below re-anchor themselves onto the map. --}}
<div id="ops-stage">
    <div class="page-toolbar d-flex justify-content-between align-items-center flex-wrap gap-2">
        <p class="text-muted mb-0">
            <strong id="ops-count">{{ count($vehicles) }}</strong> {{ Str::plural('vehicle', count($vehicles)) }} transmitting
            {{ $ctx->isSuperAdmin() ? 'across all organizations' : 'in your organization' }}
        </p>
        <span class="text-muted small">
            <i class="bi bi-arrow-repeat me-1"></i>Auto-refreshing · last update <span id="ops-updated">—</span>
        </span>
        {{-- lg and up only. Below that the map and list already stack full-width, and a
             22rem panel floating over a 390px screen would cover the map it floats on. --}}
        <button type="button" id="ops-expand"
                class="btn btn-sm btn-outline-secondary d-none d-lg-inline-flex align-items-center">
            <i class="bi bi-arrows-fullscreen me-1"></i><span id="ops-expand-text">Expand map</span>
        </button>
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

    <div class="d-flex flex-wrap gap-3 small mb-3 ops-legend">
        {{-- Same five the passenger sees; mirrors VehicleFreshnessPalette and
             OperationsController::classify. Change all three together. --}}
        <span><span class="ops-dot me-1" style="background:#15A04A"></span>Live (≤30s)</span>
        <span><span class="ops-dot me-1" style="background:#6FBF5E"></span>Recent (≤2m)</span>
        <span><span class="ops-dot me-1" style="background:#E89C12"></span>Delayed (≤5m)</span>
        <span><span class="ops-dot me-1" style="background:#E0512B"></span>Stale (≤15m)</span>
        <span><span class="ops-dot me-1" style="background:#7E8A97"></span>Offline (&gt;15m)</span>
    </div>

    <div class="row g-3">
        <div class="col-12 col-lg-8 ops-map-col">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-2">
                    <div id="ops-map"></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-4 ops-list-col">
            <div class="card border-0 shadow-sm">
                {{-- Only rendered while expanded: parked over the map, a 22rem panel is
                     in the way the moment you want to see what is behind it. In the
                     normal two-column layout it sits beside the map and there is
                     nothing to get out of the way of. --}}
                <button type="button" id="ops-list-toggle"
                        class="btn btn-sm w-100 d-none justify-content-between align-items-center
                               border-0 border-bottom rounded-0 fw-semibold px-3 py-2">
                    <span><i class="bi bi-list-ul me-2"></i>Vehicles</span>
                    <i class="bi bi-chevron-up" id="ops-list-caret"></i>
                </button>
                <div id="ops-list">
                    <div id="ops-list-body"></div>
                </div>
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

            // 6s in production, 20s under `php artisan serve` — the built-in dev server
            // handles ONE request at a time, so a fast poll there occupies the worker
            // the admin's next page load needs. Apache in the shipped container runs a
            // pool and has no such problem. The notification bell splits for the same
            // reason.
            //
            // 6s is not a guess. DriverLocationForegroundService publishes to
            // vehicle_live_location every LIVE_SYNC_INTERVAL_MS = 5s, so at the previous
            // 12s this map discarded better than half of the positions the buses had
            // already spent battery and data to send. Matching the publish cadence shows
            // what was actually reported; polling faster than 5s would return the same
            // row twice and buy nothing.
            var POLL_MS = {{ PHP_SAPI === 'cli-server' ? 20000 : 6000 }};

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
                if (timer === null) timer = setInterval(poll, POLL_MS);
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


            // ── Expanded ("theatre") mode ────────────────────────────────────
            // The stage is pinned over the content column rather than the viewport, so
            // the measurements come from the live sidebar and page header: the sidebar
            // has three widths (rail / md / lg) and the header row wraps on a narrow
            // window, which rules out a constant.
            const stage = document.getElementById('ops-stage');
            const expandBtn = document.getElementById('ops-expand');
            const expandText = document.getElementById('ops-expand-text');
            const listToggle = document.getElementById('ops-list-toggle');
            const listCaret = document.getElementById('ops-list-caret');

            function syncStageOffsets() {
                const nav = document.getElementById('adminSidebar');
                const header = document.querySelector('main > .d-flex');
                // Below md the sidebar is an off-canvas drawer sitting off screen, so its
                // right edge is negative and the stage should start at the window edge.
                const left = (nav && window.innerWidth >= 768)
                    ? Math.max(0, nav.getBoundingClientRect().right) : 0;
                const top = header ? Math.max(0, header.getBoundingClientRect().bottom) : 0;
                stage.style.setProperty('--ops-left', left + 'px');
                stage.style.setProperty('--ops-top', top + 'px');
            }

            // Leaflet caches its container size. Resize the container without telling it
            // and you get grey tiles and a map centred on the wrong place -- so this has
            // to run AFTER the layout has settled, not in the same tick as the class flip.
            // Leaflet's top-left corner holds the zoom control, and the floating toolbar
            // and chips land on top of it. Push the corner below whichever reaches lowest.
            function syncControlInset() {
                const stageTop = stage.getBoundingClientRect().top;
                let lowest = 0;
                [stage.querySelector('.page-toolbar'), document.getElementById('ops-filter')]
                    .forEach(function (el) {
                        if (!el) return;
                        const r = el.getBoundingClientRect();
                        if (r.height) lowest = Math.max(lowest, r.bottom - stageTop);
                    });
                stage.style.setProperty('--ops-ctrl-top', (lowest + 12) + 'px');
            }

            function remeasureMap() {
                syncControlInset();
                map.invalidateSize();
                const shown = activeRoute ? latest.filter(v => v.route === activeRoute) : latest;
                if (!shown || !shown.length) return;
                // Re-fit: the viewport just changed size by a lot, and the old zoom would
                // leave every bus huddled in the middle of a much bigger map. Asymmetric
                // padding keeps them out from under the floating panel on the right.
                const on = stage.classList.contains('ops-on');
                map.fitBounds(shown.map(v => [v.lat, v.lng]), {
                    paddingTopLeft: [40, on ? 100 : 40],
                    paddingBottomRight: [on && !stage.classList.contains('ops-list-closed') ? 380 : 40, 60],
                    maxZoom: 14
                });
            }

            function setExpanded(on) {
                stage.classList.toggle('ops-on', on);
                // Measured after the flip, not before: going fixed + overflow:hidden
                // removes the page scrollbar, which shifts the sidebar's right edge.
                if (on) syncStageOffsets();
                expandBtn.setAttribute('aria-pressed', on ? 'true' : 'false');
                expandText.textContent = on ? 'Exit' : 'Expand map';
                expandBtn.querySelector('i').className =
                    on ? 'bi bi-fullscreen-exit me-1' : 'bi bi-arrows-fullscreen me-1';
                requestAnimationFrame(function () { requestAnimationFrame(remeasureMap); });
            }

            if (expandBtn) {
                expandBtn.addEventListener('click', function () {
                    setExpanded(!stage.classList.contains('ops-on'));
                });
                document.addEventListener('keydown', function (e) {
                    if (e.key === 'Escape' && stage.classList.contains('ops-on')) setExpanded(false);
                });
                // Collapsing the sidebar moves the stage's left edge, and it is a CSS
                // width change, which fires no event of its own -- hence the custom one.
                window.addEventListener('amoec:nav-rail', function () {
                    if (stage.classList.contains('ops-on')) { syncStageOffsets(); remeasureMap(); }
                });
                window.addEventListener('resize', function () {
                    if (!stage.classList.contains('ops-on')) return;
                    // Dropping below lg removes the expand button's own breakpoint; leaving
                    // the stage pinned there would float a 22rem panel over a phone-width map.
                    if (window.innerWidth < 992) { setExpanded(false); return; }
                    syncStageOffsets();
                    remeasureMap();
                });
            }

            if (listToggle) {
                listToggle.addEventListener('click', function () {
                    const closed = stage.classList.toggle('ops-list-closed');
                    listCaret.className = closed ? 'bi bi-chevron-down' : 'bi bi-chevron-up';
                    listToggle.setAttribute('aria-expanded', closed ? 'false' : 'true');
                });
            }

            if (document.visibilityState === 'visible') start();
            setTimeout(function () { map.invalidateSize(); }, 200);
        });
    </script>
@endpush
