{{-- Shared organization fields. Expects $organization (model, possibly new). --}}
@php $status = (string) old('status', $organization->status ?? 'active'); @endphp

<div class="row">
    <div class="col-md-4 mb-3">
        <label for="slug" class="form-label">Slug @unless($organization->exists)<span class="text-danger">*</span>@endunless</label>
        @if ($organization->exists)
            <input type="text" id="slug" value="{{ $organization->slug }}" class="form-control" disabled readonly>
            <div class="form-text">The slug is the stable tenant key and can't be changed.</div>
        @else
            <input type="text" name="slug" id="slug" value="{{ old('slug', $organization->slug) }}"
                   class="form-control @error('slug') is-invalid @enderror"
                   required minlength="2" maxlength="50" autofocus placeholder="metro-transit">
            @error('slug') <div class="invalid-feedback">{{ $message }}</div> @enderror
            <div class="form-text">Lowercase letters, numbers, hyphens. Set once.</div>
        @endif
    </div>
    <div class="col-md-8 mb-3">
        <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
        <input type="text" name="name" id="name" value="{{ old('name', $organization->name) }}"
               class="form-control @error('name') is-invalid @enderror"
               required minlength="2" maxlength="120" placeholder="Metro Transit Cooperative">
        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<div class="row">
    <div class="col-md-8 mb-3">
        <label for="legal_name" class="form-label">Legal name</label>
        <input type="text" name="legal_name" id="legal_name" value="{{ old('legal_name', $organization->legal_name) }}"
               class="form-control @error('legal_name') is-invalid @enderror"
               maxlength="200" placeholder="optional — registered legal entity name">
        @error('legal_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4 mb-3">
        <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
        <select name="status" id="status"
                class="form-select @error('status') is-invalid @enderror" required>
            @foreach (\App\Models\Organization::STATUSES as $value => $label)
                <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
        <div class="form-text">Suspended tenants are hidden from the mobile app.</div>
    </div>
</div>

<hr class="my-4">
<h2 class="h6 text-muted mb-3"><i class="bi bi-palette me-1"></i>Branding</h2>

<div class="row">
    <div class="col-md-4 mb-3">
        <label for="primary_color" class="form-label">Primary colour</label>
        <div class="input-group">
            <input type="color" class="form-control form-control-color" value="{{ old('primary_color', $organization->primary_color) ?: '#0d6efd' }}"
                   oninput="document.getElementById('primary_color').value = this.value" aria-label="Primary colour picker">
            <input type="text" name="primary_color" id="primary_color" value="{{ old('primary_color', $organization->primary_color) }}"
                   class="form-control @error('primary_color') is-invalid @enderror" placeholder="#0d6efd" maxlength="7">
            @error('primary_color') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <label for="secondary_color" class="form-label">Secondary colour</label>
        <div class="input-group">
            <input type="color" class="form-control form-control-color" value="{{ old('secondary_color', $organization->secondary_color) ?: '#6c757d' }}"
                   oninput="document.getElementById('secondary_color').value = this.value" aria-label="Secondary colour picker">
            <input type="text" name="secondary_color" id="secondary_color" value="{{ old('secondary_color', $organization->secondary_color) }}"
                   class="form-control @error('secondary_color') is-invalid @enderror" placeholder="#6c757d" maxlength="7">
            @error('secondary_color') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <label for="logo_url" class="form-label">Logo URL</label>
        <input type="url" name="logo_url" id="logo_url" value="{{ old('logo_url', $organization->logo_url) }}"
               class="form-control @error('logo_url') is-invalid @enderror"
               maxlength="500" placeholder="https://…/logo.png">
        @error('logo_url') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>
<p class="text-muted small mb-0">Leave a colour blank to use the app default. Hex format <code>#rrggbb</code>.</p>

@if ($organization->exists)
    <div class="mt-3">
        <label for="logo_file" class="form-label">Upload logo</label>
        <div class="d-flex align-items-center gap-3">
            @if ($organization->logo_url)
                <img src="{{ $organization->logo_url }}" alt="Current logo"
                     style="width:48px;height:48px;object-fit:contain;background:#fff;border:1px solid #e5e7eb;border-radius:.5rem;">
            @endif
            <div class="flex-grow-1">
                <input type="file" name="logo_file" id="logo_file"
                       accept="image/png,image/jpeg,image/webp"
                       class="form-control @error('logo_file') is-invalid @enderror">
                @error('logo_file') <div class="invalid-feedback">{{ $message }}</div> @enderror
                <div class="form-text">PNG, JPG or WebP up to 1 MB. A file replaces the Logo URL above and shows in the sidebar.</div>
            </div>
        </div>
        @if ($organization->logo_url)
            <div class="form-check mt-2">
                <input class="form-check-input" type="checkbox" name="remove_logo" id="remove_logo" value="1">
                <label class="form-check-label small text-danger" for="remove_logo">
                    Remove the current logo (revert to the initials mark)
                </label>
            </div>
        @endif
    </div>
@endif

<hr class="my-4">
<h2 class="h6 text-muted mb-3"><i class="bi bi-geo me-1"></i>Region &amp; map</h2>

<div class="row">
    <div class="col-md-4 mb-3">
        <label for="region_center_lat" class="form-label">Region center latitude</label>
        <input type="number" step="any" min="-90" max="90" name="region_center_lat" id="region_center_lat"
               value="{{ old('region_center_lat', $organization->region_center_lat) }}"
               class="form-control @error('region_center_lat') is-invalid @enderror" placeholder="12.2445">
        @error('region_center_lat') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4 mb-3">
        <label for="region_center_lng" class="form-label">Region center longitude</label>
        <input type="number" step="any" min="-180" max="180" name="region_center_lng" id="region_center_lng"
               value="{{ old('region_center_lng', $organization->region_center_lng) }}"
               class="form-control @error('region_center_lng') is-invalid @enderror" placeholder="124.5765">
        @error('region_center_lng') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4 mb-3">
        <label for="map_style_url" class="form-label">Map style URL</label>
        <input type="url" name="map_style_url" id="map_style_url" value="{{ old('map_style_url', $organization->map_style_url) }}"
               class="form-control @error('map_style_url') is-invalid @enderror"
               maxlength="500" placeholder="https://…/style.json">
        @error('map_style_url') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>
<p class="text-muted small mb-0">
    <i class="bi bi-info-circle me-1"></i>The region bounding box is set with the map editor and isn't edited here.
    Center coordinates come as a pair — set both or neither.
</p>

@include('partials.region-map-picker')

<hr class="my-4">
<h2 class="h6 text-muted mb-3"><i class="bi bi-envelope me-1"></i>Contact</h2>

<div class="row">
    <div class="col-md-6 mb-3">
        <label for="contact_email" class="form-label">Contact email</label>
        <input type="email" name="contact_email" id="contact_email" value="{{ old('contact_email', $organization->contact_email) }}"
               class="form-control @error('contact_email') is-invalid @enderror"
               maxlength="200" placeholder="ops@example.coop">
        @error('contact_email') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6 mb-3">
        <label for="contact_phone" class="form-label">Contact phone</label>
        <input type="text" name="contact_phone" id="contact_phone" value="{{ old('contact_phone', $organization->contact_phone) }}"
               class="form-control @error('contact_phone') is-invalid @enderror"
               maxlength="40" placeholder="+63 …">
        @error('contact_phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>
