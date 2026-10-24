@extends('layouts.app')

@section('title', 'Driver performance')
@section('heading', 'Driver performance')
@section('nav_analytics_drivers', 'active')

@php
    $labels = [
        'received' => 'received', 'under_review' => 'under review', 'upheld' => 'upheld',
        'not_upheld' => 'not upheld', 'outcome_not_recorded' => 'outcome not recorded', 'dismissed' => 'dismissed',
    ];
    $activePreset = ($preset !== null && in_array((int) $preset, \App\Support\ScorecardPolicy::PRESETS, true))
        ? (int) $preset : null;
    $partialTotal = collect($rows)->sum('partial');
@endphp

@section('content')
    <div class="page-toolbar d-flex justify-content-between align-items-start flex-wrap gap-2">
        <p class="text-muted mb-0">
            How each driver's trips went, and what passengers reported, between
            <strong>{{ $from->format('M j, Y') }}</strong> and <strong>{{ $to->format('M j, Y') }}</strong>.
            <br>
            <span class="small">
                <i class="bi bi-arrow-repeat me-1"></i>Updated hourly
                @if ($lastComputed)
                    &middot; last computed {{ $lastComputed->format('H:i') }}
                @endif
                &middot; trips under 30 minutes old are not in yet.
            </span>
        </p>
        <span class="badge text-bg-light border"><i class="bi bi-shield-lock me-1"></i>Read-only</span>
    </div>

    {{-- Period and route. Plain GET links and one small form, so the whole view is addressable
         by URL and the browser back button works. --}}
    <form method="GET" action="{{ route('analytics.drivers.index') }}" class="row g-2 align-items-end mb-3">
        <div class="col-auto">
            <div class="btn-group" role="group" aria-label="Period">
                @foreach (\App\Support\ScorecardPolicy::PRESETS as $days)
                    <a href="{{ route('analytics.drivers.index', array_filter(['preset' => $days, 'route' => $routeId])) }}"
                       class="btn btn-sm {{ ($activePreset === $days || ($activePreset === null && $days === 30 && ! request()->has('from'))) ? 'btn-primary' : 'btn-outline-secondary' }}">
                        {{ $days }} days
                    </a>
                @endforeach
            </div>
        </div>
        <div class="col-auto">
            <label class="form-label small mb-0" for="from">From</label>
            <input type="date" class="form-control form-control-sm" id="from" name="from" value="{{ $from->format('Y-m-d') }}">
        </div>
        <div class="col-auto">
            <label class="form-label small mb-0" for="to">To</label>
            <input type="date" class="form-control form-control-sm" id="to" name="to" value="{{ $to->format('Y-m-d') }}">
        </div>
        <div class="col-auto">
            <label class="form-label small mb-0" for="route">Route</label>
            <select class="form-select form-select-sm" id="route" name="route">
                <option value="">All routes</option>
                @foreach ($routes as $r)
                    <option value="{{ $r->id }}" @selected($routeId === $r->id)>{{ $r->code }} &middot; {{ $r->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-sm btn-brand">Apply</button>
        </div>
    </form>

    <div class="card border-0 shadow-sm">
        @include('partials.table-search', ['placeholder' => 'Search drivers…'])
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Driver</th>
                        <th class="text-end">Trips</th>
                        <th class="text-end">On time</th>
                        <th class="text-end">Early / late</th>
                        <th class="text-end">Avg speed</th>
                        <th class="text-end">Over-speed</th>
                        <th>Complaints</th>
                        <th class="text-end">Safety</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td class="min-w-0">
                                <div class="fw-semibold text-truncate">{{ $row['name'] }}</div>
                                @if ($row['employee_code'] && $row['employee_code'] !== $row['name'])
                                    <div class="small text-muted">{{ $row['employee_code'] }}</div>
                                @endif
                            </td>
                            <td class="text-end">
                                @if ($row['trips'] === 0)
                                    <span class="text-muted small">no trips</span>
                                @else
                                    {{ $row['trips'] }}
                                    <div class="small text-muted">{{ $row['hours'] }} h &middot; {{ $row['distance_km'] }} km</div>
                                @endif
                            </td>
                            <td class="text-end">
                                @if ($row['on_time_pct'] !== null)
                                    {{ rtrim(rtrim(number_format($row['on_time_pct'], 1), '0'), '.') }}%
                                    <div class="small text-muted">of {{ $row['scored'] }} scored</div>
                                @elseif ($row['trips'] > 0)
                                    <span class="text-muted">&mdash;</span>
                                    <div class="small text-muted">no timetable</div>
                                @else
                                    <span class="text-muted">&mdash;</span>
                                @endif
                            </td>
                            <td class="text-end">{{ $row['early'] }} / {{ $row['late'] }}</td>
                            <td class="text-end">
                                @if ($row['avg_speed_kmh'] !== null)
                                    {{ round($row['avg_speed_kmh']) }} km/h
                                    <div class="small text-muted">top {{ round($row['max_speed_kmh']) }}</div>
                                @else
                                    <span class="text-muted">&mdash;</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @if ($overspeedLimit === null)
                                    <span class="text-muted small">limit not set</span>
                                @elseif ($row['overspeed_share'] !== null)
                                    {{ round($row['overspeed_share'] * 100) }}%
                                    <div class="small text-muted">of readings over {{ $overspeedLimit }}</div>
                                @else
                                    <span class="text-muted">&mdash;</span>
                                @endif
                            </td>
                            <td>
                                @php $c = $row['complaints']; @endphp
                                @if ($c['total'] === 0)
                                    <span class="text-muted">0</span>
                                @else
                                    <strong>{{ $c['total'] }}</strong>
                                    <div class="small text-muted">
                                        {{ collect($labels)->filter(fn ($l, $k) => $c[$k] > 0)->map(fn ($l, $k) => $c[$k].' '.$l)->implode(' · ') }}
                                    </div>
                                @endif
                                @if ($row['rate_per_100'] !== null)
                                    <div class="small text-muted">{{ $row['rate_per_100'] }} per 100 trips</div>
                                @elseif ($row['trips'] > 0 && $c['total'] > 0)
                                    <div class="small text-muted">too few trips to compare</div>
                                @endif
                            </td>
                            <td class="text-end">{{ $row['safety_related'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-5">
                                <i class="bi bi-people d-block mb-3" style="font-size:1.75rem;opacity:.45"></i>
                                No drivers to show for this period.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="small text-muted mt-3">
        <p class="mb-1">
            <i class="bi bi-info-circle me-1"></i>
            Speed and over-speed figures come from phone GPS, so they are <strong>indicative, not evidence</strong>.
            A complaint is a passenger's report, not a finding: only <em>upheld</em> ones were judged true, and
            complaints about the vehicle or the route are not counted against the driver.
        </p>
        @if ($unattributed > 0)
            <p class="mb-1">
                <i class="bi bi-question-circle me-1"></i>
                {{ $unattributed }} conduct {{ \Illuminate\Support\Str::plural('report', $unattributed) }} in this
                period named no trip, so {{ $unattributed === 1 ? 'it is' : 'they are' }} not counted against any driver.
            </p>
        @endif
        @if ($partialTotal > 0)
            <p class="mb-1">
                <i class="bi bi-exclamation-triangle me-1"></i>
                {{ $partialTotal }} {{ \Illuminate\Support\Str::plural('trip', $partialTotal) }} have partial GPS data
                (a gap at the start, or too few readings), so their speed figures are left out or less reliable.
            </p>
        @endif
        <p class="mb-0">
            A rate per 100 trips is shown only from {{ $minTripsForRate }} trips. History before the first
            hourly run begins at the earliest retained data.
        </p>
    </div>
@endsection
