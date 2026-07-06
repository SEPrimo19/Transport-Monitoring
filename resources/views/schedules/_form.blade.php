{{-- Shared schedule fields. Expects $schedule, $routes, $organizations. --}}
@php
    $mode = old('mode', $schedule->mode ?? 'interval');
    $active = (string) old('is_active', $schedule->is_active ? '1' : '0');
    $selectedDays = collect(old('days_of_week', $schedule->days_of_week ?? []))->map(fn ($d) => (int) $d)->all();
    $startVal = old('start_time', $schedule->start_time ? substr($schedule->start_time, 0, 5) : '');
    $endVal = old('end_time', $schedule->end_time ? substr($schedule->end_time, 0, 5) : '');
    $depTimes = old('departure_times', $schedule->departure_times ?? []);
    $depTimesStr = is_array($depTimes) ? implode(', ', $depTimes) : $depTimes;
@endphp

<div class="row">
    <div class="col-md-6 mb-3">
        <label for="route_id" class="form-label">Route <span class="text-danger">*</span></label>
        <select name="route_id" id="route_id" class="form-select @error('route_id') is-invalid @enderror" required>
            <option value="">— select a route —</option>
            @foreach ($routes as $r)
                <option value="{{ $r->id }}" @selected(old('route_id', $schedule->route_id) === $r->id)>{{ $r->code }} — {{ $r->name }}</option>
            @endforeach
        </select>
        @error('route_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6 mb-3">
        <label for="label" class="form-label">Label</label>
        <input type="text" name="label" id="label" value="{{ old('label', $schedule->label) }}"
               class="form-control @error('label') is-invalid @enderror" maxlength="80" placeholder="optional — e.g. Weekday service">
        @error('label') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<div class="mb-3">
    <label class="form-label d-block">Days of week <span class="text-danger">*</span></label>
    <div class="btn-group flex-wrap" role="group">
        @foreach (\App\Models\TripSchedule::DAYS as $num => $abbr)
            <input type="checkbox" class="btn-check" name="days_of_week[]" value="{{ $num }}" id="day{{ $num }}"
                   @checked(in_array($num, $selectedDays, true))>
            <label class="btn btn-outline-primary btn-sm" for="day{{ $num }}">{{ $abbr }}</label>
        @endforeach
    </div>
    @error('days_of_week') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
</div>

<div class="row">
    <div class="col-md-4 mb-3">
        <label for="mode" class="form-label">Mode <span class="text-danger">*</span></label>
        <select name="mode" id="mode" class="form-select @error('mode') is-invalid @enderror" required onchange="scheduleToggleMode()">
            <option value="interval" @selected($mode === 'interval')>Interval (every N min)</option>
            <option value="fixed" @selected($mode === 'fixed')>Fixed times</option>
        </select>
        @error('mode') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4 mb-3">
        <label for="is_active" class="form-label">Status <span class="text-danger">*</span></label>
        <select name="is_active" id="is_active" class="form-select" required>
            <option value="1" @selected($active === '1')>Active</option>
            <option value="0" @selected($active === '0')>Inactive</option>
        </select>
        <div class="form-text">Inactive timetables are hidden from passengers.</div>
    </div>
</div>

{{-- Interval mode --}}
<div id="interval-fields" class="row">
    <div class="col-md-4 mb-3">
        <label for="start_time" class="form-label">First departure</label>
        <input type="time" name="start_time" id="start_time" value="{{ $startVal }}"
               class="form-control @error('start_time') is-invalid @enderror">
        @error('start_time') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4 mb-3">
        <label for="end_time" class="form-label">Last departure</label>
        <input type="time" name="end_time" id="end_time" value="{{ $endVal }}"
               class="form-control @error('end_time') is-invalid @enderror">
        @error('end_time') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4 mb-3">
        <label for="interval_minutes" class="form-label">Every … minutes</label>
        <input type="number" name="interval_minutes" id="interval_minutes" min="1" max="1440"
               value="{{ old('interval_minutes', $schedule->interval_minutes) }}"
               class="form-control @error('interval_minutes') is-invalid @enderror" placeholder="30">
        @error('interval_minutes') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

{{-- Fixed mode --}}
<div id="fixed-fields" class="mb-3">
    <label for="departure_times" class="form-label">Departure times</label>
    <input type="text" name="departure_times" id="departure_times" value="{{ $depTimesStr }}"
           class="form-control @error('departure_times') is-invalid @enderror @error('departure_times.*') is-invalid @enderror"
           placeholder="06:00, 09:00, 12:00, 15:00, 18:00">
    @error('departure_times') <div class="invalid-feedback">{{ $message }}</div> @enderror
    @error('departure_times.*') <div class="invalid-feedback">{{ $message }}</div> @enderror
    <div class="form-text">Comma-separated 24-hour times, e.g. <code>06:00, 09:00, 12:00</code>.</div>
</div>

@if ($ctx->isSuperAdmin())
    <div class="mb-3">
        <label for="organization_id" class="form-label">Organization <span class="text-danger">*</span></label>
        <select name="organization_id" id="organization_id" class="form-select @error('organization_id') is-invalid @enderror" required>
            <option value="">— select an organization —</option>
            @foreach ($organizations as $org)
                <option value="{{ $org->id }}" @selected(old('organization_id', $schedule->organization_id) === $org->id)>{{ $org->name }}</option>
            @endforeach
        </select>
        @error('organization_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
        <div class="form-text">The route above must belong to this organization.</div>
    </div>
@endif

@push('scripts')
    <script>
        function scheduleToggleMode() {
            var mode = document.getElementById('mode').value;
            document.getElementById('interval-fields').style.display = mode === 'interval' ? '' : 'none';
            document.getElementById('fixed-fields').style.display = mode === 'fixed' ? '' : 'none';
        }
        document.addEventListener('DOMContentLoaded', scheduleToggleMode);
    </script>
@endpush
