{{-- Shared terminal fields. Expects $terminal (model, possibly new), $organizations. --}}
@php $active = (string) old('is_active', $terminal->is_active ? '1' : '0'); @endphp

<div class="row">
    <div class="col-md-4 mb-3">
        <label for="code" class="form-label">Code <span class="text-danger">*</span></label>
        <input type="text" name="code" id="code" value="{{ old('code', $terminal->code) }}"
               class="form-control @error('code') is-invalid @enderror"
               required minlength="2" maxlength="32" autofocus placeholder="ALN">
        @error('code') <div class="invalid-feedback">{{ $message }}</div> @enderror
        <div class="form-text">Short code, unique across the system.</div>
    </div>
    <div class="col-md-8 mb-3">
        <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
        <input type="text" name="name" id="name" value="{{ old('name', $terminal->name) }}"
               class="form-control @error('name') is-invalid @enderror"
               required minlength="2" maxlength="120" placeholder="Allen Terminal">
        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<div class="mb-3">
    <label for="address" class="form-label">Address</label>
    <input type="text" name="address" id="address" value="{{ old('address', $terminal->address) }}"
           class="form-control @error('address') is-invalid @enderror"
           maxlength="255" placeholder="optional — street / barangay, municipality">
    @error('address') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="row">
    <div class="col-md-4 mb-3">
        <label for="latitude" class="form-label">Latitude</label>
        <input type="number" step="any" min="-90" max="90" name="latitude" id="latitude"
               value="{{ old('latitude', $terminal->latitude) }}"
               class="form-control @error('latitude') is-invalid @enderror" placeholder="12.5057">
        @error('latitude') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4 mb-3">
        <label for="longitude" class="form-label">Longitude</label>
        <input type="number" step="any" min="-180" max="180" name="longitude" id="longitude"
               value="{{ old('longitude', $terminal->longitude) }}"
               class="form-control @error('longitude') is-invalid @enderror" placeholder="124.2861">
        @error('longitude') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4 mb-3">
        <label for="is_active" class="form-label">Status <span class="text-danger">*</span></label>
        <select name="is_active" id="is_active"
                class="form-select @error('is_active') is-invalid @enderror" required>
            <option value="1" @selected($active === '1')>Active</option>
            <option value="0" @selected($active === '0')>Inactive</option>
        </select>
        @error('is_active') <div class="invalid-feedback">{{ $message }}</div> @enderror
        <div class="form-text">Inactive terminals are hidden from passengers.</div>
    </div>
</div>
<p class="text-muted small mb-2">
    <i class="bi bi-geo-alt me-1"></i>Coordinates are optional but come as a pair — set both or neither.
</p>

{{-- Location picker: search or click the map to fill the latitude/longitude above --}}
<div class="mb-3">
    <label class="form-label">Find location</label>
    <div class="input-group">
        <input type="text" id="place-search" class="form-control" placeholder="Search a place or address (e.g. “Allen, Northern Samar”)…" autocomplete="off">
        <button type="button" id="place-search-btn" class="btn btn-outline-secondary">
            <i class="bi bi-search me-1"></i>Search
        </button>
    </div>
    <div id="place-results" class="list-group mt-1"></div>
    <div id="terminal-map" class="mt-2 rounded border" style="height: 340px;"></div>
    <div class="form-text">
        Search for the place, or click the map to drop a pin — it fills the latitude and longitude above.
        Drag the pin to fine-tune. No need to look coordinates up elsewhere.
    </div>
</div>

@if ($ctx->isSuperAdmin())
    <div class="mb-3">
        <label for="organization_id" class="form-label">Organization <span class="text-danger">*</span></label>
        <select name="organization_id" id="organization_id"
                class="form-select @error('organization_id') is-invalid @enderror" required>
            <option value="">— select an organization —</option>
            @foreach ($organizations as $org)
                <option value="{{ $org->id }}" @selected(old('organization_id', $terminal->organization_id) === $org->id)>{{ $org->name }}</option>
            @endforeach
        </select>
        @error('organization_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
@endif

@push('head')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css">
@endpush

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const latEl = document.getElementById('latitude');
            const lngEl = document.getElementById('longitude');
            const mapEl = document.getElementById('terminal-map');
            if (!mapEl || !latEl || !lngEl) return;

            const DEFAULT_CENTER = [12.4, 124.55]; // Samar fallback; search covers anywhere
            const haveLat = latEl.value !== '' && !isNaN(parseFloat(latEl.value));
            const haveLng = lngEl.value !== '' && !isNaN(parseFloat(lngEl.value));

            const map = L.map('terminal-map').setView(
                (haveLat && haveLng) ? [parseFloat(latEl.value), parseFloat(lngEl.value)] : DEFAULT_CENTER,
                (haveLat && haveLng) ? 15 : 8
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
                if (recenter) map.setView([lat, lng], Math.max(map.getZoom(), 15));
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
            const searchEl = document.getElementById('place-search');
            const searchBtn = document.getElementById('place-search-btn');
            const resultsEl = document.getElementById('place-results');

            async function doSearch() {
                const q = searchEl.value.trim();
                if (!q) return;
                resultsEl.innerHTML = '<div class="list-group-item small text-muted">Searching…</div>';
                try {
                    const url = 'https://nominatim.openstreetmap.org/search?format=json&limit=6&q=' + encodeURIComponent(q);
                    const resp = await fetch(url, { headers: { 'Accept': 'application/json' } });
                    const data = await resp.json();
                    if (!Array.isArray(data) || data.length === 0) {
                        resultsEl.innerHTML = '<div class="list-group-item small text-muted">No matches. Try a different search, or click the map.</div>';
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
