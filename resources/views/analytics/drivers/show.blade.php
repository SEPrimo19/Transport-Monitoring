@extends('layouts.app')

@section('title', $row['name'].' · Driver performance')
@section('heading', 'Driver performance')
@section('nav_analytics_drivers', 'active')

@php
    $punctuality = [
        'on_time' => ['On time', 'text-bg-success'], 'early' => ['Early', 'text-bg-info'],
        'late' => ['Late', 'text-bg-warning'], 'no_timetable' => ['No timetable', 'text-bg-light border'],
        'unscheduled' => ['Unscheduled', 'text-bg-light border'],
    ];
    $c = $row['complaints'];
    $complaintLabels = [
        'received' => 'received', 'under_review' => 'under review', 'upheld' => 'upheld',
        'not_upheld' => 'not upheld', 'outcome_not_recorded' => 'outcome not recorded', 'dismissed' => 'dismissed',
    ];
@endphp

@section('content')
    <div class="page-toolbar d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div class="min-w-0">
            <a href="{{ route('analytics.drivers.index', $periodQuery) }}" class="small text-decoration-none">
                <i class="bi bi-arrow-left me-1"></i>All drivers
            </a>
            <h2 class="h4 mb-0 mt-1 text-truncate">{{ $row['name'] }}</h2>
            <div class="text-muted small">
                @if ($row['employee_code'] && $row['employee_code'] !== $row['name']) {{ $row['employee_code'] }} &middot; @endif
                {{ $from->format('M j, Y') }} &ndash; {{ $to->format('M j, Y') }}
            </div>
        </div>
        <span class="badge text-bg-light border"><i class="bi bi-shield-lock me-1"></i>Read-only</span>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100"><div class="card-body">
                <div class="text-muted small">Trips</div>
                <div class="h4 mb-0">{{ $row['trips'] }}</div>
                <div class="small text-muted">{{ $row['hours'] }} h &middot; {{ $row['distance_km'] }} km</div>
            </div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100"><div class="card-body">
                <div class="text-muted small">On time</div>
                <div class="h4 mb-0">
                    @if ($row['on_time_pct'] !== null)
                        {{ rtrim(rtrim(number_format($row['on_time_pct'], 1), '0'), '.') }}%
                    @else
                        &mdash;
                    @endif
                </div>
                <div class="small text-muted">
                    @if ($row['scored'] > 0)
                        of {{ $row['scored'] }} scored &middot; {{ $row['early'] }} early, {{ $row['late'] }} late
                    @elseif ($row['trips'] > 0)
                        no timetable to score against
                    @endif
                </div>
            </div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100"><div class="card-body">
                <div class="text-muted small">Speed (indicative)</div>
                <div class="h4 mb-0">
                    @if ($row['avg_speed_kmh'] !== null) {{ round($row['avg_speed_kmh']) }} km/h @else &mdash; @endif
                </div>
                <div class="small text-muted">
                    @if ($row['max_speed_kmh'] !== null) top {{ round($row['max_speed_kmh']) }} &middot; @endif
                    @if ($overspeedLimit === null)
                        over-speed: limit not set
                    @elseif ($row['overspeed_share'] !== null)
                        {{ round($row['overspeed_share'] * 100) }}% of readings over {{ $overspeedLimit }}
                    @else
                        over-speed: no data
                    @endif
                </div>
            </div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100"><div class="card-body">
                <div class="text-muted small">Complaints</div>
                <div class="h4 mb-0">{{ $c['total'] }}</div>
                <div class="small text-muted">
                    @if ($row['rate_per_100'] !== null)
                        {{ $row['rate_per_100'] }} per 100 trips
                    @elseif ($c['total'] > 0)
                        too few trips to compare
                    @endif
                    @if ($row['safety_related'] > 0) &middot; {{ $row['safety_related'] }} safety-related @endif
                </div>
            </div></div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body pb-0"><h3 class="h6 mb-0">By route</h3></div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light"><tr>
                    <th>Route</th><th class="text-end">Trips</th><th class="text-end">On time</th><th class="text-end">Avg speed</th>
                </tr></thead>
                <tbody>
                    @forelse ($byRoute as $r)
                        <tr>
                            <td>{{ $r['code'] }}</td>
                            <td class="text-end">{{ $r['trips'] }}</td>
                            <td class="text-end">
                                @if ($r['on_time_pct'] !== null) {{ rtrim(rtrim(number_format($r['on_time_pct'], 1), '0'), '.') }}% @else <span class="text-muted">&mdash;</span> @endif
                            </td>
                            <td class="text-end">
                                @if ($r['avg_speed_kmh'] !== null) {{ $r['avg_speed_kmh'] }} km/h @else <span class="text-muted">&mdash;</span> @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-4">No trips in this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3" id="complaints">
        <div class="card-body pb-0">
            <h3 class="h6 mb-1">Complaints behind the count</h3>
            <p class="small text-muted mb-2">
                @if ($c['total'] > 0)
                    {{ collect($complaintLabels)->filter(fn ($l, $k) => $c[$k] > 0)->map(fn ($l, $k) => $c[$k].' '.$l)->implode(' · ') }}.
                @endif
                A complaint is a passenger report, not a finding. Only <em>upheld</em> ones were judged true.
            </p>
        </div>
        <div class="list-group list-group-flush">
            @forelse ($complaints as $report)
                <a href="{{ route('reports.show', $report) }}" class="list-group-item list-group-item-action">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <div class="min-w-0">
                            <span class="fw-semibold">{{ $report->categoryLabel() }}</span>
                            @if ($report->category === 'safety') <span class="badge text-bg-light border ms-1">safety-related</span> @endif
                            @if ($report->is_anonymous) <span class="badge text-bg-light border ms-1">anonymous</span> @endif
                            <div class="small text-muted">{{ \Illuminate\Support\Carbon::parse($report->created_at)->format('M j, Y g:i A') }}</div>
                        </div>
                        <div class="text-end small flex-shrink-0">
                            <span class="badge {{ $report->statusBadge() }}">{{ $report->statusLabel() }}</span>
                            @if ($report->outcomeLabel() !== '')
                                <div class="text-muted mt-1">{{ $report->outcomeLabel() }}</div>
                            @endif
                        </div>
                    </div>
                </a>
            @empty
                <div class="list-group-item text-center text-muted py-4">No complaints in this period.</div>
            @endforelse
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body pb-0"><h3 class="h6 mb-0">Trips</h3></div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light"><tr>
                    <th>Started</th><th>Route</th><th>Departure</th><th class="text-end">Duration</th>
                    <th class="text-end">Distance</th><th class="text-end">Avg speed</th><th>Data</th>
                </tr></thead>
                <tbody>
                    @forelse ($trips as $t)
                        @php [$plabel, $pclass] = $punctuality[$t->punctuality] ?? [$t->punctuality, 'text-bg-light border']; @endphp
                        <tr>
                            <td class="text-nowrap">{{ $t->started_at?->format('M j, g:i A') }}</td>
                            <td>{{ $t->route?->code ?? '—' }}</td>
                            <td>
                                <span class="badge {{ $pclass }}">{{ $plabel }}</span>
                                @if (in_array($t->punctuality, ['on_time', 'early', 'late'], true) && $t->departure_delta_min !== null)
                                    <span class="small text-muted">{{ $t->departure_delta_min > 0 ? '+' : '' }}{{ $t->departure_delta_min }} min</span>
                                @endif
                            </td>
                            <td class="text-end">{{ $t->duration_min }} min</td>
                            <td class="text-end">{{ $t->distance_km !== null ? $t->distance_km.' km' : '—' }}</td>
                            <td class="text-end">{{ $t->avg_moving_speed_kmh !== null ? round($t->avg_moving_speed_kmh).' km/h' : '—' }}</td>
                            <td>
                                @if ($t->data_quality === 'partial')
                                    <span class="badge text-bg-light border" title="Gap at the start or too few GPS readings">partial</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">No trips in this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($trips->hasPages())
            <div class="card-body">{{ $trips->links() }}</div>
        @endif
    </div>
@endsection
