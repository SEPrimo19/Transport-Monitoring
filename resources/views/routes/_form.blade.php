{{-- Shared route fields. Expects $route (model, possibly new), $terminals, $organizations. --}}
@php
    $active = (string) old('is_active', $route->is_active ? '1' : '0');
    $superAdmin = $ctx->isSuperAdmin();
    $orgNames = $superAdmin ? $organizations->pluck('name', 'id') : collect();
    // Group terminals by org so super_admin can tell which org each terminal belongs to
    // (org_admin sees a single flat list). Cross-org picks are still rejected server-side.
    $terminalGroups = $superAdmin ? $terminals->groupBy('organization_id') : collect(['' => $terminals]);
@endphp

<div class="row">
    <div class="col-md-4 mb-3">
        <label for="code" class="form-label">Code <span class="text-danger">*</span></label>
        <input type="text" name="code" id="code" value="{{ old('code', $route->code) }}"
               class="form-control @error('code') is-invalid @enderror"
               required minlength="2" maxlength="32" autofocus placeholder="ALN-CAL">
        @error('code') <div class="invalid-feedback">{{ $message }}</div> @enderror
        <div class="form-text">Unique across the system.</div>
    </div>
    <div class="col-md-8 mb-3">
        <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
        <input type="text" name="name" id="name" value="{{ old('name', $route->name) }}"
               class="form-control @error('name') is-invalid @enderror"
               required minlength="2" maxlength="120" placeholder="Terminal A to Terminal B">
        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<div class="row">
    <div class="col-md-6 mb-3">
        <label for="origin_terminal_id" class="form-label">Origin terminal <span class="text-danger">*</span></label>
        <select name="origin_terminal_id" id="origin_terminal_id"
                class="form-select @error('origin_terminal_id') is-invalid @enderror" required>
            <option value="">— select —</option>
            @foreach ($terminalGroups as $orgId => $group)
                @if ($superAdmin)<optgroup label="{{ $orgNames[$orgId] ?? 'Unknown org' }}">@endif
                @foreach ($group as $t)
                    <option value="{{ $t->id }}"
                            @if ($t->latitude !== null && $t->longitude !== null) data-lat="{{ $t->latitude }}" data-lng="{{ $t->longitude }}" @endif
                            @selected(old('origin_terminal_id', $route->origin_terminal_id) === $t->id)>{{ $t->code }} — {{ $t->name }}</option>
                @endforeach
                @if ($superAdmin)</optgroup>@endif
            @endforeach
        </select>
        @error('origin_terminal_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6 mb-3">
        <label for="destination_terminal_id" class="form-label">Destination terminal <span class="text-danger">*</span></label>
        <select name="destination_terminal_id" id="destination_terminal_id"
                class="form-select @error('destination_terminal_id') is-invalid @enderror" required>
            <option value="">— select —</option>
            @foreach ($terminalGroups as $orgId => $group)
                @if ($superAdmin)<optgroup label="{{ $orgNames[$orgId] ?? 'Unknown org' }}">@endif
                @foreach ($group as $t)
                    <option value="{{ $t->id }}"
                            @if ($t->latitude !== null && $t->longitude !== null) data-lat="{{ $t->latitude }}" data-lng="{{ $t->longitude }}" @endif
                            @selected(old('destination_terminal_id', $route->destination_terminal_id) === $t->id)>{{ $t->code }} — {{ $t->name }}</option>
                @endforeach
                @if ($superAdmin)</optgroup>@endif
            @endforeach
        </select>
        @error('destination_terminal_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<div class="row">
    <div class="col-md-4 mb-3">
        <label for="distance_km" class="form-label">Distance (km) <span class="text-danger">*</span></label>
        <input type="number" step="0.01" min="0.01" name="distance_km" id="distance_km"
               value="{{ old('distance_km', $route->distance_km) }}"
               class="form-control @error('distance_km') is-invalid @enderror" required>
        @error('distance_km') <div class="invalid-feedback">{{ $message }}</div> @enderror
        {{-- Nobody knows the road distance when first adding a route, but the column
             is NOT NULL, so this field used to force a guess. Both terminals carry
             coordinates, so offer the straight-line distance as a starting figure —
             and say plainly that the polyline editor replaces it with the real road
             distance, so an approximation here costs nothing. --}}
        <div class="form-text" id="distance-hint">
            Not sure? Draw the route on the map afterwards and this is replaced by the
            real road distance.
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <label for="duration_minutes" class="form-label">Duration (min)</label>
        <input type="number" step="1" min="1" name="duration_minutes" id="duration_minutes"
               value="{{ old('duration_minutes', $route->duration_minutes) }}"
               class="form-control @error('duration_minutes') is-invalid @enderror" placeholder="optional">
        @error('duration_minutes') <div class="invalid-feedback">{{ $message }}</div> @enderror
        @if (($durationStats ?? null))
            <div class="form-text">
                <i class="bi bi-graph-up me-1"></i>Real average from {{ $durationStats['count'] }}
                recent {{ Str::plural('trip', $durationStats['count']) }}:
                <strong>{{ $durationStats['avg_minutes'] }} min</strong>.
                <button type="button" class="btn btn-link btn-sm p-0 align-baseline"
                        onclick="document.getElementById('duration_minutes').value = {{ $durationStats['avg_minutes'] }}">Use this</button>
            </div>
        @endif
    </div>
    <div class="col-md-4 mb-3">
        <label for="is_active" class="form-label">Status <span class="text-danger">*</span></label>
        <select name="is_active" id="is_active"
                class="form-select @error('is_active') is-invalid @enderror" required>
            <option value="1" @selected($active === '1')>Active</option>
            <option value="0" @selected($active === '0')>Inactive</option>
        </select>
        @error('is_active') <div class="invalid-feedback">{{ $message }}</div> @enderror
        <div class="form-text">Inactive routes are hidden from passengers.</div>
    </div>
</div>

@if ($ctx->isSuperAdmin())
    <div class="mb-3">
        <label for="organization_id" class="form-label">Organization <span class="text-danger">*</span></label>
        <select name="organization_id" id="organization_id"
                class="form-select @error('organization_id') is-invalid @enderror" required>
            <option value="">— select an organization —</option>
            @foreach ($organizations as $org)
                <option value="{{ $org->id }}" @selected(old('organization_id', $route->organization_id) === $org->id)>{{ $org->name }}</option>
            @endforeach
        </select>
        @error('organization_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
        <div class="form-text">Terminals above must belong to this organization.</div>
    </div>
@endif

@push('scripts')
<script>
    /* Straight-line suggestion for Distance (km).
       The terminals carry coordinates, so once both ends are chosen we can offer a
       great-circle figure. It is deliberately labelled "straight-line" and never
       written into the field without a click: real road distance is always longer,
       and the polyline editor overwrites this value with the routed figure anyway. */
    (function () {
        var origin = document.getElementById('origin_terminal_id');
        var dest = document.getElementById('destination_terminal_id');
        var field = document.getElementById('distance_km');
        var hint = document.getElementById('distance-hint');
        if (!origin || !dest || !field || !hint) return;

        var baseText = hint.textContent.trim();

        function coords(sel) {
            var o = sel.options[sel.selectedIndex];
            if (!o || !o.dataset.lat || !o.dataset.lng) return null;
            return { lat: parseFloat(o.dataset.lat), lng: parseFloat(o.dataset.lng) };
        }

        function haversineKm(a, b) {
            var R = 6371, rad = Math.PI / 180;
            var dLat = (b.lat - a.lat) * rad, dLng = (b.lng - a.lng) * rad;
            var h = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                    Math.cos(a.lat * rad) * Math.cos(b.lat * rad) *
                    Math.sin(dLng / 2) * Math.sin(dLng / 2);
            return 2 * R * Math.asin(Math.sqrt(h));
        }

        function update() {
            var a = coords(origin), b = coords(dest);
            if (!a || !b || (a.lat === b.lat && a.lng === b.lng)) {
                hint.textContent = baseText;
                return;
            }
            var km = haversineKm(a, b);
            if (!isFinite(km) || km <= 0) { hint.textContent = baseText; return; }
            var shown = km.toFixed(2);
            hint.innerHTML = 'Straight line between these terminals is about <strong>' +
                shown + ' km</strong> — the road is longer. ' +
                '<a href="#" id="use-estimate">Use ' + shown + '</a>. ' + baseText;
            document.getElementById('use-estimate').addEventListener('click', function (e) {
                e.preventDefault();
                field.value = shown;
                field.focus();
            });
        }

        origin.addEventListener('change', update);
        dest.addEventListener('change', update);
        update();
    })();
</script>
@endpush
