{{-- Shared assignment fields. Expects $assignment, $drivers, $vehicles, $routes, $organizations. --}}
@php
    $active = (string) old('is_active', $assignment->is_active ? '1' : '0');
    $starts = old('starts_at', optional($assignment->starts_at)->format('Y-m-d\TH:i'));
    $ends = old('ends_at', optional($assignment->ends_at)->format('Y-m-d\TH:i'));
    // Group each list by org so super_admin can pick matching entities per organization
    // (org_admin sees a single flat list). Cross-org combos are still rejected server-side.
    $superAdmin = $ctx->isSuperAdmin();
    $orgNames = $superAdmin ? $organizations->pluck('name', 'id') : collect();
    $driverGroups = $superAdmin ? $drivers->groupBy('organization_id') : collect(['' => $drivers]);
    $vehicleGroups = $superAdmin ? $vehicles->groupBy('organization_id') : collect(['' => $vehicles]);
    $routeGroups = $superAdmin ? $routes->groupBy('organization_id') : collect(['' => $routes]);
@endphp

<div class="mb-3">
    <label for="driver_profile_id" class="form-label">Driver <span class="text-danger">*</span></label>
    <select name="driver_profile_id" id="driver_profile_id" data-searchable
            data-search-placeholder="Type a driver's name or code…"
            class="form-select @error('driver_profile_id') is-invalid @enderror" required>
        <option value="">— select a driver —</option>
        @foreach ($driverGroups as $orgId => $group)
            @if ($superAdmin)<optgroup label="{{ $orgNames[$orgId] ?? 'Unknown org' }}">@endif
            @foreach ($group as $d)
                <option value="{{ $d->profile_id }}" @selected(old('driver_profile_id', $assignment->driver_profile_id) === $d->profile_id)>
                    {{ $d->profile?->full_name ?? '(unnamed)' }}@if ($d->employee_code) — {{ $d->employee_code }}@endif
                </option>
            @endforeach
            @if ($superAdmin)</optgroup>@endif
        @endforeach
    </select>
    @error('driver_profile_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="row">
    <div class="col-md-6 mb-3">
        <label for="vehicle_id" class="form-label">Vehicle <span class="text-danger">*</span></label>
        <select name="vehicle_id" id="vehicle_id" data-searchable
                data-search-placeholder="Type a plate or body number…"
                class="form-select @error('vehicle_id') is-invalid @enderror" required>
            <option value="">— select a vehicle —</option>
            @foreach ($vehicleGroups as $orgId => $group)
                @if ($superAdmin)<optgroup label="{{ $orgNames[$orgId] ?? 'Unknown org' }}">@endif
                @foreach ($group as $v)
                    <option value="{{ $v->id }}" @selected(old('vehicle_id', $assignment->vehicle_id) === $v->id)>
                        {{ $v->plate_number }}@if ($v->fleet_code) ({{ $v->fleet_code }})@endif
                    </option>
                @endforeach
                @if ($superAdmin)</optgroup>@endif
            @endforeach
        </select>
        @error('vehicle_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6 mb-3">
        <label for="route_id" class="form-label">Route <span class="text-danger">*</span></label>
        <select name="route_id" id="route_id"
                class="form-select @error('route_id') is-invalid @enderror" required>
            <option value="">— select a route —</option>
            @foreach ($routeGroups as $orgId => $group)
                @if ($superAdmin)<optgroup label="{{ $orgNames[$orgId] ?? 'Unknown org' }}">@endif
                @foreach ($group as $r)
                    <option value="{{ $r->id }}" @selected(old('route_id', $assignment->route_id) === $r->id)>
                        {{ $r->code }} — {{ $r->name }}
                    </option>
                @endforeach
                @if ($superAdmin)</optgroup>@endif
            @endforeach
        </select>
        @error('route_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<div class="row">
    <div class="col-md-4 mb-3">
        <label for="starts_at" class="form-label">Valid from <span class="text-danger">*</span></label>
        <input type="datetime-local" name="starts_at" id="starts_at" value="{{ $starts }}"
               class="form-control @error('starts_at') is-invalid @enderror" required>
        @error('starts_at') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4 mb-3">
        <label for="ends_at" class="form-label">Valid until <span class="text-danger">*</span></label>
        <input type="datetime-local" name="ends_at" id="ends_at" value="{{ $ends }}"
               class="form-control @error('ends_at') is-invalid @enderror" required>
        @error('ends_at') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4 mb-3">
        <label for="is_active" class="form-label">Status <span class="text-danger">*</span></label>
        <select name="is_active" id="is_active"
                class="form-select @error('is_active') is-invalid @enderror" required>
            <option value="1" @selected($active === '1')>Active</option>
            <option value="0" @selected($active === '0')>Inactive</option>
        </select>
        @error('is_active') <div class="invalid-feedback">{{ $message }}</div> @enderror
        <div class="form-text">One active assignment per driver and per vehicle on each route. The same driver and bus can hold both directions of a corridor.</div>
    </div>
</div>

@if ($ctx->isSuperAdmin())
    <div class="mb-3">
        <label for="organization_id" class="form-label">Organization <span class="text-danger">*</span></label>
        <select name="organization_id" id="organization_id"
                class="form-select @error('organization_id') is-invalid @enderror" required>
            <option value="">— select an organization —</option>
            @foreach ($organizations as $org)
                <option value="{{ $org->id }}" @selected(old('organization_id', $assignment->organization_id) === $org->id)>{{ $org->name }}</option>
            @endforeach
        </select>
        @error('organization_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
        <div class="form-text">The driver, vehicle and route above must belong to this organization.</div>
    </div>
@endif

@push('scripts')
    <script id="assignment-taken" type="application/json">@json($takenOnRoute ?? [])</script>
    <script>
        /* Mark the driver and vehicle already taken ON THE CHOSEN ROUTE.
           ----------------------------------------------------------------
           The database enforces uq_assignments_active_driver_route and
           uq_assignments_active_vehicle_route, so the clash is per route, not
           per vehicle. A bus on Allen→Calbayog is still free for
           Calbayog→Allen — the normal shape of a corridor, and what the hint
           under Status describes. Marking a bus taken outright would hide half
           the legitimate work, so nothing is flagged until a route is picked,
           and the flags are recomputed whenever it changes.

           Advisory only. The server still validates, and the unique indexes
           are the real guarantee; this just stops an admin filling in a form
           that was always going to be rejected. */
        (function () {
            var holder = document.getElementById('assignment-taken');
            if (!holder) return;

            var taken;
            try { taken = JSON.parse(holder.textContent || '{}'); } catch (e) { return; }

            var routeSel = document.getElementById('route_id');
            var driverSel = document.getElementById('driver_profile_id');
            var vehicleSel = document.getElementById('vehicle_id');
            if (!routeSel || !driverSel || !vehicleSel) return;

            var byRoute = taken.byRoute || {};
            var summary = taken.summary || { drivers: {}, vehicles: {} };

            function mark(select, blockedById, summaryById, prefix) {
                Array.prototype.forEach.call(select.options, function (opt) {
                    if (!opt.value) return;
                    var blockedBy = blockedById ? blockedById[opt.value] : null;

                    // Never disable what is already chosen. On the edit screen the
                    // current pairing is legitimately "taken" by this very assignment,
                    // and greying it out would make saving an unrelated field
                    // impossible.
                    if (blockedBy && opt.value !== select.value) {
                        opt.dataset.ssNote = prefix + ' ' + blockedBy;
                        opt.dataset.ssNoteTone = 'blocked';
                        opt.disabled = true;
                        return;
                    }

                    opt.disabled = false;

                    // Not blocked here, but say what it IS doing. Without this the list
                    // read as entirely free until a route was picked -- including a bus
                    // already running both directions, which is as booked as a vehicle
                    // can be.
                    var busy = summaryById ? summaryById[opt.value] : null;
                    if (busy && opt.value !== select.value) {
                        opt.dataset.ssNote = busy;
                        opt.dataset.ssNoteTone = 'info';
                    } else {
                        delete opt.dataset.ssNote;
                        delete opt.dataset.ssNoteTone;
                    }
                });
            }

            function refresh() {
                var forRoute = byRoute[routeSel.value] || { drivers: {}, vehicles: {} };
                mark(driverSel, forRoute.drivers, summary.drivers, 'already on');
                mark(vehicleSel, forRoute.vehicles, summary.vehicles, 'already with');
            }

            routeSel.addEventListener('change', refresh);
            refresh();
        })();
    </script>
@endpush
