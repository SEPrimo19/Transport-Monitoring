{{-- Shared vehicle fields. Expects $vehicle (model, possibly new) + $organizations. --}}
<div class="mb-3">
    <label for="plate_number" class="form-label">Plate number <span class="text-danger">*</span></label>
    <input type="text" name="plate_number" id="plate_number"
           value="{{ old('plate_number', $vehicle->plate_number) }}"
           class="form-control @error('plate_number') is-invalid @enderror"
           required minlength="4" maxlength="32" autofocus>
    @error('plate_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
    <div class="form-text">4–32 characters. Must be unique across the system.</div>
</div>

<div class="mb-3">
    <label for="fleet_code" class="form-label">Body number</label>
    <input type="text" name="fleet_code" id="fleet_code"
           value="{{ old('fleet_code', $vehicle->fleet_code) }}"
           class="form-control @error('fleet_code') is-invalid @enderror"
           minlength="2" maxlength="40">
    @error('fleet_code') <div class="invalid-feedback">{{ $message }}</div> @enderror
    <div class="form-text">The number painted on the vehicle body. Optional, 2–40 characters if set.</div>
</div>

<div class="mb-3">
    <label for="capacity_total" class="form-label">Capacity <span class="text-danger">*</span></label>
    <input type="number" name="capacity_total" id="capacity_total"
           value="{{ old('capacity_total', $vehicle->capacity_total) }}"
           class="form-control @error('capacity_total') is-invalid @enderror"
           required min="1" max="200">
    @error('capacity_total') <div class="invalid-feedback">{{ $message }}</div> @enderror
    <div class="form-text">Total seats/standing capacity.</div>
</div>

<div class="mb-3">
    <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
    <select name="status" id="status" class="form-select @error('status') is-invalid @enderror" required>
        @foreach (\App\Models\Vehicle::STATUSES as $status)
            <option value="{{ $status }}" @selected(old('status', $vehicle->status) === $status)>{{ ucfirst($status) }}</option>
        @endforeach
    </select>
    @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
    <div class="form-text">
        Only <strong>Active</strong> vehicles are visible to passengers on the map
        (this drives the <code>is_active</code> flag the mobile app reads).
    </div>
</div>

@if ($ctx->isSuperAdmin())
    <div class="mb-3">
        <label for="organization_id" class="form-label">Organization <span class="text-danger">*</span></label>
        <select name="organization_id" id="organization_id"
                class="form-select @error('organization_id') is-invalid @enderror" required>
            <option value="">— select an organization —</option>
            @foreach ($organizations as $org)
                <option value="{{ $org->id }}" @selected(old('organization_id', $vehicle->organization_id) === $org->id)>{{ $org->name }}</option>
            @endforeach
        </select>
        @error('organization_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
        <div class="form-text">As a super admin you must choose which tenant owns this vehicle.</div>
    </div>
@endif
