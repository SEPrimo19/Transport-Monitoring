@extends('layouts.app')

@section('title', $row['name'].' · Driver performance')
@section('heading', 'Driver performance')
@section('nav_analytics_drivers', 'active')

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
        <a href="{{ route('report-center.drivers', ['driver' => $row['driver_id']] + $periodQuery) }}" class="btn btn-sm btn-brand">
            <i class="bi bi-file-earmark-text me-1"></i>Generate report
        </a>
    </div>

    @include('partials.driver.performance-cards', ['row' => $row, 'overspeedLimit' => $overspeedLimit])

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

    @include('partials.driver.complaints', ['row' => $row, 'complaints' => $complaints])

    @include('partials.driver.trips', ['trips' => $trips])
@endsection
