{{-- Region-center map picker. Expects inputs #region_center_lat and
     #region_center_lng to already exist on the page (the Region & map section).
     Search or click the map to fill them; drag the pin to fine-tune. Reuses the
     same Leaflet + Nominatim approach as the terminal picker, with its own
     element ids so the two never collide on a page. --}}
<div class="mt-3">
    <label class="form-label">Pin the region center</label>
    <div class="input-group">
        <input type="text" id="region-place-search" class="form-control"
               placeholder="Search a place…" autocomplete="off">
        <button type="button" id="region-place-search-btn" class="btn btn-outline-secondary">
            <i class="bi bi-search me-1"></i>Search
        </button>
    </div>
    <div id="region-place-results" class="list-group mt-1"></div>
    <div id="region-map" class="mt-2 rounded border" style="height: 340px;"></div>
    <div class="form-text">
        Search for the place or click the map to drop the pin — it fills the center latitude and longitude above.
        Drag the pin to fine-tune. No need to look coordinates up elsewhere.
    </div>
</div>

@push('head')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css">
@endpush

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const latEl = document.getElementById('region_center_lat');
            const lngEl = document.getElementById('region_center_lng');
            const mapEl = document.getElementById('region-map');
            if (!mapEl || !latEl || !lngEl || typeof L === 'undefined') return;

            const DEFAULT_CENTER = [12.4, 124.55]; // Samar fallback; search covers anywhere
            const haveLat = latEl.value !== '' && !isNaN(parseFloat(latEl.value));
            const haveLng = lngEl.value !== '' && !isNaN(parseFloat(lngEl.value));

            const map = L.map('region-map').setView(
                (haveLat && haveLng) ? [parseFloat(latEl.value), parseFloat(lngEl.value)] : DEFAULT_CENTER,
                (haveLat && haveLng) ? 13 : 8
            );
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19, attribution: '&copy; OpenStreetMap contributors'
            }).addTo(map);

            let marker = null;
            function round6(n) { return Math.round(n * 1e6) / 1e6; }
            function writeInputs(lat, lng) { latEl.value = round6(lat); lngEl.value = round6(lng); }
            function setMarker(lat, lng, recenter) {
                if (marker) {
                    marker.setLatLng([lat, lng]);
                } else {
                    marker = L.marker([lat, lng], { draggable: true }).addTo(map);
                    marker.on('dragend', function () {
                        const p = marker.getLatLng();
                        writeInputs(p.lat, p.lng);
                    });
                }
                if (recenter) map.setView([lat, lng], Math.max(map.getZoom(), 13));
            }
            if (haveLat && haveLng) setMarker(parseFloat(latEl.value), parseFloat(lngEl.value), false);

            map.on('click', function (e) {
                setMarker(e.latlng.lat, e.latlng.lng, false);
                writeInputs(e.latlng.lat, e.latlng.lng);
            });

            // Typing/pasting coordinates moves the pin.
            function fromInputs() {
                const la = parseFloat(latEl.value), ln = parseFloat(lngEl.value);
                if (!isNaN(la) && !isNaN(ln) && la >= -90 && la <= 90 && ln >= -180 && ln <= 180) {
                    setMarker(la, ln, true);
                }
            }
            latEl.addEventListener('change', fromInputs);
            lngEl.addEventListener('change', fromInputs);

            // Nominatim (OpenStreetMap) place search — user-triggered to respect the
            // usage policy (no keystroke autocomplete). CORS-enabled, no API key.
            const searchEl = document.getElementById('region-place-search');
            const searchBtn = document.getElementById('region-place-search-btn');
            const resultsEl = document.getElementById('region-place-results');

            async function doSearch() {
                const q = searchEl.value.trim();
                if (!q) return;
                resultsEl.innerHTML = '<div class="list-group-item small text-muted">Searching…</div>';
                try {
                    const url = 'https://nominatim.openstreetmap.org/search?format=json&limit=6&q=' + encodeURIComponent(q);
                    const resp = await fetch(url, { headers: { 'Accept': 'application/json' } });
                    const data = await resp.json();
                    if (!Array.isArray(data) || data.length === 0) {
                        resultsEl.innerHTML = '<div class="list-group-item small text-muted">No matches. Try another search, or click the map.</div>';
                        return;
                    }
                    resultsEl.innerHTML = '';
                    data.forEach(function (r) {
                        const btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'list-group-item list-group-item-action small';
                        btn.textContent = r.display_name;
                        btn.addEventListener('click', function () {
                            const lat = parseFloat(r.lat), lng = parseFloat(r.lon);
                            setMarker(lat, lng, true);
                            writeInputs(lat, lng);
                            resultsEl.innerHTML = '';
                            searchEl.value = r.display_name.split(',')[0];
                        });
                        resultsEl.appendChild(btn);
                    });
                } catch (e) {
                    resultsEl.innerHTML = '<div class="list-group-item small text-danger">Search failed. Check your connection, or click the map instead.</div>';
                }
            }
            searchBtn.addEventListener('click', doSearch);
            searchEl.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') { e.preventDefault(); doSearch(); }
            });

            setTimeout(function () { map.invalidateSize(); }, 200);
        });
    </script>
@endpush
