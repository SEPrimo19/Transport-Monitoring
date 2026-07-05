@extends('layouts.app')

@section('title', 'Route polyline')
@section('heading', 'Route polyline')
@section('nav_routes', 'active')

@php $canRoute = $origin && $dest; @endphp

@push('head')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@geoman-io/leaflet-geoman-free@2.17.0/dist/leaflet-geoman.css">
    <style>
        #map { height: 66vh; min-height: 420px; width: 100%; border-radius: .5rem; }
        .leaflet-container { font: inherit; }
        .via-tip { background: #fff; border: 1px solid #6c757d; color: #495057; font-size: .72rem; padding: 0 .3rem; }
    </style>
@endpush

@section('content')
    <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
        <div>
            <span class="badge text-bg-light border fs-6">{{ $route->code }}</span>
            <span class="ms-1">{{ $route->name }}</span>
            <div class="text-muted small mt-1">
                {{ $route->originTerminal?->code ?? '—' }}
                <i class="bi bi-arrow-right mx-1"></i>
                {{ $route->destinationTerminal?->code ?? '—' }}
            </div>
        </div>
        <a href="{{ route('routes.index') }}" class="btn btn-sm btn-light">
            <i class="bi bi-arrow-left me-1"></i>Back to routes
        </a>
    </div>

    <div class="alert alert-light border small mb-3">
        <strong><i class="bi bi-magic me-1"></i>Fastest way</strong>
        Click <strong>Generate route along roads</strong> to trace the roads between the two terminals automatically, then <strong>Save</strong>.
        If it picks the wrong road, turn on <strong>via-points</strong>, click a point on the correct road, and generate again.
        <hr class="my-2">
        <strong><i class="bi bi-pencil-square me-1"></i>Or draw by hand</strong>
        Use the <strong>draw line</strong> tool (top-left): click to add points, click the last point again or press <kbd>Enter</kbd> to finish.
        <strong>Edit</strong> drags vertices, <strong>Drag</strong> moves the whole line, <strong>Delete</strong> removes it.
        Order matters — go from <span class="text-success fw-semibold">origin</span> to <span class="text-danger fw-semibold">destination</span>.
    </div>

    {{-- Routing toolbar --}}
    <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
        <button id="generate-btn" type="button" class="btn btn-brand"
                @unless($canRoute) disabled title="Set both terminals’ coordinates to enable road generation" @endunless>
            <i class="bi bi-magic me-1"></i>Generate route along roads
        </button>
        <button id="via-toggle" type="button" class="btn btn-outline-secondary"
                @unless($canRoute) disabled @endunless>
            <i class="bi bi-plus-circle me-1"></i>Add via-points: <span id="via-state">off</span>
        </button>
        <button id="clear-via" type="button" class="btn btn-outline-secondary" disabled>
            <i class="bi bi-x-circle me-1"></i>Clear via-points
        </button>
        <span id="route-info" class="text-muted small"></span>
    </div>
    @unless($canRoute)
        <p class="text-warning-emphasis small mb-2">
            <i class="bi bi-exclamation-triangle me-1"></i>
            One or both terminals have no coordinates, so automatic generation is off. Set their lat/lng on the Terminals screen, or draw the line by hand.
        </p>
    @endunless
    <div id="route-error" class="alert alert-danger py-2 small d-none"></div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body p-2">
            <div id="map"></div>
        </div>
    </div>

    <div class="d-flex align-items-center flex-wrap gap-2">
        <form id="geometry-form" method="POST" action="{{ route('routes.geometry.update', $route) }}" class="m-0">
            @csrf
            @method('PUT')
            <input type="hidden" name="points" id="points-field">
            <button id="save-btn" type="submit" class="btn btn-primary" disabled>
                <i class="bi bi-check-lg me-1"></i>Save polyline
            </button>
        </form>

        @if ($route->geometry)
            <form method="POST" action="{{ route('routes.geometry.destroy', $route) }}" class="m-0"
                  onsubmit="return confirm('Remove this route’s polyline? The mobile map overlay for {{ $route->code }} will be blank until you draw a new one.');">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-outline-danger">
                    <i class="bi bi-trash me-1"></i>Remove polyline
                </button>
            </form>
        @endif

        <span class="text-muted ms-1">
            <i class="bi bi-bezier2 me-1"></i><strong id="point-count">0</strong> points
            @if ($route->geometry?->source)
                <span class="ms-2">· source: <code>{{ $route->geometry->source }}</code></span>
            @endif
        </span>

        <a href="{{ route('routes.index') }}" class="btn btn-light ms-auto">Cancel</a>
    </div>

    @error('points')
        <div class="alert alert-danger mt-3 mb-0"><i class="bi bi-exclamation-triangle me-1"></i>{{ $message }}</div>
    @enderror
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@geoman-io/leaflet-geoman-free@2.17.0/dist/leaflet-geoman.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const EXISTING = @json($points);          // [[lat,lng],...] or null
            const ORIGIN = @json($origin);            // {lat,lng,code} or null
            const DEST = @json($dest);                // {lat,lng,code} or null
            const DEFAULT_CENTER = @json($defaultCenter);

            // Public OSRM demo server (no key). Swap this base to a self-hosted /
            // proxied routing endpoint later. Coordinates are lng,lat for OSRM.
            const OSRM_BASE = 'https://router.project-osrm.org';

            const map = L.map('map').setView(DEFAULT_CENTER, 9);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(map);

            function addTerminal(t, color, label) {
                if (!t || t.lat == null || t.lng == null) return;
                L.circleMarker([t.lat, t.lng], {
                    radius: 8, color: color, weight: 3,
                    fillColor: color, fillOpacity: 0.9, pmIgnore: true
                }).addTo(map).bindTooltip(label + ': ' + t.code);
            }
            addTerminal(ORIGIN, '#198754', 'Origin');
            addTerminal(DEST, '#dc3545', 'Destination');

            map.pm.addControls({
                position: 'topleft',
                drawPolyline: true,
                editMode: true,
                dragMode: true,
                removalMode: true,
                drawMarker: false, drawCircleMarker: false, drawPolygon: false,
                drawRectangle: false, drawCircle: false, drawText: false,
                cutPolygon: false, rotateMode: false
            });
            map.pm.setGlobalOptions({ snappable: true, snapDistance: 20 });

            const countEl = document.getElementById('point-count');
            const saveBtn = document.getElementById('save-btn');
            let routeLine = null;

            function updateCount() {
                const n = routeLine ? routeLine.getLatLngs().length : 0;
                countEl.textContent = n;
                saveBtn.disabled = n < 2;
            }
            function bindLine(line) {
                line.on('pm:edit pm:update pm:vertexadded pm:vertexremoved pm:markerdragend', updateCount);
            }
            function setRouteLine(latlngs) {
                if (routeLine) map.removeLayer(routeLine);
                routeLine = L.polyline(latlngs, { color: '#0d6efd', weight: 4 }).addTo(map);
                bindLine(routeLine);
                updateCount();
                return routeLine;
            }

            if (EXISTING && EXISTING.length) {
                setRouteLine(EXISTING);
                map.fitBounds(routeLine.getBounds(), { padding: [30, 30] });
            } else {
                const ends = [];
                if (ORIGIN && ORIGIN.lat != null) ends.push([ORIGIN.lat, ORIGIN.lng]);
                if (DEST && DEST.lat != null) ends.push([DEST.lat, DEST.lng]);
                if (ends.length === 2) map.fitBounds(ends, { padding: [50, 50] });
                else if (ends.length === 1) map.setView(ends[0], 12);
            }

            // Manual draw: a fresh line replaces the old one (a route has one path).
            map.on('pm:create', function (e) {
                if (e.shape !== 'Line') return;
                if (routeLine && routeLine !== e.layer) map.removeLayer(routeLine);
                routeLine = e.layer;
                bindLine(routeLine);
                updateCount();
            });
            map.on('pm:remove', function (e) {
                if (e.layer === routeLine) { routeLine = null; updateCount(); }
            });

            // ── Via-points ────────────────────────────────────────────────────
            let viaMarkers = [];
            let addingVia = false;
            const viaToggle = document.getElementById('via-toggle');
            const viaState = document.getElementById('via-state');
            const clearViaBtn = document.getElementById('clear-via');

            function renumberVias() {
                viaMarkers.forEach((m, i) => m.setTooltipContent('via ' + (i + 1)));
            }
            function updateViaUI() {
                clearViaBtn.disabled = viaMarkers.length === 0;
            }
            function addVia(latlng) {
                const m = L.marker(latlng, { draggable: true, pmIgnore: true }).addTo(map);
                m.bindTooltip('via ' + (viaMarkers.length + 1), { permanent: true, direction: 'top', className: 'via-tip' });
                m.on('click', function (ev) {
                    L.DomEvent.stopPropagation(ev);
                    map.removeLayer(m);
                    viaMarkers = viaMarkers.filter(x => x !== m);
                    renumberVias();
                    updateViaUI();
                });
                viaMarkers.push(m);
                updateViaUI();
            }
            viaToggle.addEventListener('click', function () {
                addingVia = !addingVia;
                viaState.textContent = addingVia ? 'on' : 'off';
                viaToggle.classList.toggle('active', addingVia);
                viaToggle.classList.toggle('btn-secondary', addingVia);
                viaToggle.classList.toggle('btn-outline-secondary', !addingVia);
                if (addingVia) { map.pm.disableDraw(); map.pm.disableGlobalEditMode(); map.pm.disableGlobalDragMode(); map.pm.disableGlobalRemovalMode(); }
            });
            clearViaBtn.addEventListener('click', function () {
                viaMarkers.forEach(m => map.removeLayer(m));
                viaMarkers = [];
                updateViaUI();
            });
            map.on('click', function (e) {
                if (addingVia) addVia(e.latlng);
            });

            // ── Generate route via OSRM ──────────────────────────────────────
            const genBtn = document.getElementById('generate-btn');
            const routeInfo = document.getElementById('route-info');
            const routeError = document.getElementById('route-error');

            function showError(msg) {
                routeError.textContent = msg;
                routeError.classList.remove('d-none');
            }
            function clearError() { routeError.classList.add('d-none'); routeError.textContent = ''; }

            async function generateRoute() {
                if (!ORIGIN || !DEST || ORIGIN.lat == null || DEST.lat == null) return;
                clearError();
                const coords = [[ORIGIN.lng, ORIGIN.lat]];
                viaMarkers.forEach(m => { const ll = m.getLatLng(); coords.push([ll.lng, ll.lat]); });
                coords.push([DEST.lng, DEST.lat]);
                const path = coords.map(c => c[0] + ',' + c[1]).join(';');
                const url = OSRM_BASE + '/route/v1/driving/' + path + '?overview=full&geometries=geojson&alternatives=false&steps=false';

                genBtn.disabled = true;
                const original = genBtn.innerHTML;
                genBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Generating…';
                try {
                    const resp = await fetch(url);
                    const data = await resp.json();
                    if (data.code !== 'Ok' || !data.routes || !data.routes.length) {
                        showError('No road route found between these points. Add a via-point on the correct road and try again, or draw manually.');
                        return;
                    }
                    const geo = data.routes[0].geometry.coordinates;   // [[lng,lat],...]
                    const latlngs = geo.map(c => [c[1], c[0]]);        // -> [lat,lng]
                    setRouteLine(latlngs);
                    map.fitBounds(routeLine.getBounds(), { padding: [30, 30] });
                    const km = (data.routes[0].distance / 1000);
                    const min = Math.round(data.routes[0].duration / 60);
                    routeInfo.innerHTML = '<i class="bi bi-check-circle text-success me-1"></i>Generated along roads: <strong>' +
                        km.toFixed(1) + ' km</strong> · ~' + min + ' min · ' + latlngs.length +
                        ' points. Review and Save. <span class="text-muted">(you can copy this distance/duration into the route’s Edit form)</span>';
                } catch (err) {
                    showError('Could not reach the routing service (OSRM). Check your connection, or draw the line manually.');
                } finally {
                    genBtn.disabled = false;
                    genBtn.innerHTML = original;
                }
            }
            if (genBtn) genBtn.addEventListener('click', generateRoute);

            // ── Save ─────────────────────────────────────────────────────────
            document.getElementById('geometry-form').addEventListener('submit', function (ev) {
                if (!routeLine || routeLine.getLatLngs().length < 2) { ev.preventDefault(); return; }
                const pts = routeLine.getLatLngs().map(function (ll) {
                    return [Math.round(ll.lat * 1e6) / 1e6, Math.round(ll.lng * 1e6) / 1e6];
                });
                document.getElementById('points-field').value = JSON.stringify(pts);
            });

            updateCount();
            updateViaUI();
            setTimeout(function () { map.invalidateSize(); }, 200);
        });
    </script>
@endpush
