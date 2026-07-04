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
<p class="text-muted small mb-3">
    <i class="bi bi-geo-alt me-1"></i>Coordinates are optional but come as a pair — set both or neither.
</p>

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
