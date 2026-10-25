@extends('layouts.report')

@section('title', 'Dashboard summary')
@section('subtitle')
    {{ $from->format('M j, Y') }} &ndash; {{ $to->format('M j, Y') }} &middot; {{ $routeLabel }}
@endsection

@section('toolbar')
    <a class="btn" href="{{ route('report-center.summary.export', $periodQuery) }}">Export CSV</a>
    <a class="btn" href="{{ route('dashboard') }}">Back to Dashboard</a>
@endsection

@php
    $pct = static fn ($v) => $v === null ? '—' : rtrim(rtrim(number_format($v, 1), '0'), '.').'%';
    $duration = static function (?int $m): string {
        if ($m === null) return '—';
        if ($m < 60) return $m.' min';
        if ($m < 1440) return intdiv($m, 60).'h '.($m % 60).'m';
        return intdiv($m, 1440).'d '.intdiv($m % 1440, 60).'h';
    };
    $signed = static function (?int $d): string {
        if ($d === null) return '—';
        return ($d > 0 ? '+' : '').$d.' min';
    };
    $scopeText = ['driver' => 'Driver conduct', 'safety' => 'Safety, shown separately', 'not_driver' => 'Not about a driver'];

    $keyFigures = [];
    foreach ($headline as $h) {
        $value = $h['value'];
        if ($value === null) {
            $shown = '—';
        } elseif (str_contains($h['label'], 'On time')) {
            $shown = $pct($value);
        } else {
            $shown = (string) $value;
        }
        $keyFigures[] = ['label' => $h['label'], 'value' => $shown];
    }

    $nothing = $trips['figures']['trips'] === 0 && $complaints['figures']['received'] === 0;
@endphp

@section('content')
    @include('partials.report.key-figures', ['keyFigures' => $keyFigures])

    @if ($nothing)
        <p class="empty">No data for this period.</p>
    @endif

    @if ($trips['figures']['trips'] > 0)
        <h2>Trips</h2>
        <p class="sub">
            {{ $trips['figures']['trips'] }} trips &middot; {{ $trips['figures']['hours'] }} hours in service
            &middot; distance {{ $trips['figures']['distance_km'] === null ? '—' : $trips['figures']['distance_km'].' km' }}
        </p>
        <table>
            <thead>
                <tr><th>Route</th><th class="num">Trips</th><th class="num">On time</th></tr>
            </thead>
            <tbody>
                @foreach ($trips['byRoute'] as $r)
                    <tr>
                        <td>{{ $r['route_code'] }}</td>
                        <td class="num">{{ $r['trips'] }}</td>
                        <td class="num">{{ $pct($r['on_time_pct']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <h2>Drivers, all together</h2>
        <p>
            {{ $drivers['drivers_with_trips'] }} {{ \Illuminate\Support\Str::plural('driver', $drivers['drivers_with_trips']) }} ran trips.
            On time across all drivers: <strong>{{ $pct($drivers['on_time_pct']) }}</strong>.
            Complaints about drivers in this period: <strong>{{ $drivers['complaints'] }}</strong>.
        </p>
        <p class="sub">Individual drivers are not listed in this summary. They are in the Driver performance report.</p>
    @endif

    @if ($complaints['figures']['received'] > 0)
        <h2>Complaints</h2>
        <p class="sub">
            {{ $complaints['figures']['received'] }} received &middot; {{ $complaints['figures']['closed'] }} closed
            &middot; {{ $complaints['figures']['open'] }} still open
            &middot; median time to close {{ $duration($complaints['figures']['median_close_minutes']) }}
        </p>
        <table>
            <thead>
                <tr>
                    <th>Category</th>
                    <th class="num">Received</th>
                    <th class="num">Closed</th>
                    <th class="num">Still open</th>
                    <th>Counts against a driver?</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($complaints['byCategory'] as $c)
                    <tr>
                        <td>{{ $c['label'] }}</td>
                        <td class="num">{{ $c['received'] }}</td>
                        <td class="num">{{ $c['closed'] }}</td>
                        <td class="num">{{ $c['open'] }}</td>
                        <td>{{ $scopeText[$c['scope']] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>Fleet</h2>
    @if ($fleet['routeFiltered'])
        <p class="sub">Idle vehicles are not shown for a single route. Choose all routes to see them.</p>
    @elseif ($fleet['idle'] === [])
        <p class="sub">No idle vehicles: every active vehicle ran at least one trip.</p>
    @else
        <p>
            Idle vehicles ({{ count($fleet['idle']) }}):
            @foreach ($fleet['idle'] as $i){{ $i['label'] }}@if (! $loop->last), @endif @endforeach
        </p>
    @endif

    @if ($fleet['figures']['trips'] > 0)
        <table>
            <thead>
                <tr>
                    <th>Route</th>
                    <th class="num">Trips</th>
                    <th class="num">Average vs scheduled</th>
                    <th class="num">On time</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($fleet['routes'] as $r)
                    <tr>
                        <td>{{ $r['route_code'] }}</td>
                        <td class="num">{{ $r['trips'] }}</td>
                        <td class="num">{{ $signed($r['difference_min']) }}</td>
                        <td class="num">{{ $pct($r['on_time_pct']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection

@section('definitions')
    @include('partials.report.definitions')
    <ul>
        <li><strong>Every figure here is the same figure the individual reports show</strong> for the same period and route: Trips, Complaints, Vehicles and routes, and Driver performance. Open those reports for the full tables.</li>
        <li><strong>No individual is named.</strong> The Drivers section gives totals across all drivers only. No passenger, complainant or report text appears anywhere.</li>
        <li><strong>A complaint is a report, not a finding.</strong> Only reports about driver conduct are counted as complaints about drivers; safety reports are shown separately.</li>
        <li><strong>Idle vehicles</strong> are active vehicles that ran no trip in the period; they are not shown for a single route.</li>
        <li><strong>Average vs scheduled</strong> is the average trip time minus the route's configured duration; a plus sign means trips took longer than scheduled.</li>
        <li>Days are counted in {{ $timezone }} time.</li>
    </ul>
@endsection
