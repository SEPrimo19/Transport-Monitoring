@extends('layouts.report')

@section('title', 'Trips report')
@section('subtitle')
    {{ $from->format('M j, Y') }} &ndash; {{ $to->format('M j, Y') }} &middot; {{ $routeLabel }}
@endsection

@section('toolbar')
    <a class="btn" href="{{ route('report-center.trips.export', $periodQuery) }}">Export CSV</a>
    <a class="btn" href="{{ route('trips.index') }}">Back to Trips</a>
@endsection

@php
    $pct = static fn ($v) => $v === null ? '—' : rtrim(rtrim(number_format($v, 1), '0'), '.').'%';
    $duration = static function (?int $m): string {
        if ($m === null) return '—';
        return $m < 60 ? $m.' min' : intdiv($m, 60).'h '.($m % 60).'m';
    };
    $distanceLabel = 'Distance';
    if ($figures['distance_km'] === null) {
        $distanceLabel = 'Distance (not measured)';
    } elseif ($figures['distance_trips'] < $figures['trips']) {
        $distanceLabel = 'Distance (from '.$figures['distance_trips'].' of '.$figures['trips'].' trips)';
    }
    $keyFigures = [
        ['label' => 'Trips', 'value' => (string) $figures['trips']],
        ['label' => 'Hours in service', 'value' => (string) $figures['hours']],
        ['label' => $distanceLabel, 'value' => $figures['distance_km'] === null ? '—' : $figures['distance_km'].' km'],
        ['label' => 'On time (of scored trips)', 'value' => $pct($figures['on_time_pct'])],
    ];
@endphp

@section('content')
    @include('partials.report.key-figures', ['keyFigures' => $keyFigures])

    @if ($figures['trips'] === 0)
        <p class="empty">No data for this period.</p>
    @else
        <h2>By route</h2>
        <table>
            <thead>
                <tr>
                    <th>Route</th>
                    <th class="num">Trips</th>
                    <th class="num">Average duration</th>
                    <th class="num">On time</th>
                    <th class="num">Early / late</th>
                    <th class="num">Not scored</th>
                    <th class="num">Distance</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($byRoute as $r)
                    <tr>
                        <td>
                            {{ $r['route_code'] }}
                            @if ($r['route_name'] !== '')
                                <div class="sub">{{ $r['route_name'] }}</div>
                            @endif
                        </td>
                        <td class="num">{{ $r['trips'] }}</td>
                        <td class="num">{{ $duration($r['avg_duration_min']) }}</td>
                        <td class="num">
                            {{ $pct($r['on_time_pct']) }}
                            @if ($r['on_time_pct'] !== null)
                                <div class="sub">of {{ $r['on_time'] + $r['early'] + $r['late'] }} scored</div>
                            @endif
                        </td>
                        <td class="num">{{ $r['early'] }} / {{ $r['late'] }}</td>
                        <td class="num">{{ $r['no_timetable'] + $r['unscheduled'] }}</td>
                        <td class="num">
                            {{ $r['distance_km'] === null ? '—' : $r['distance_km'].' km' }}
                            @if ($r['distance_km'] !== null && $r['distance_trips'] < $r['trips'])
                                <div class="sub">from {{ $r['distance_trips'] }} of {{ $r['trips'] }} trips</div>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <h2>{{ $granularity === 'week' ? 'By week' : 'By day' }}</h2>
        <table>
            <thead>
                <tr>
                    <th>{{ $granularity === 'week' ? 'Week' : 'Day' }}</th>
                    <th class="num">Trips</th>
                    <th class="num">On time</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($byPeriod as $p)
                    <tr>
                        <td>{{ $p['label'] }}</td>
                        <td class="num">{{ $p['trips'] }}</td>
                        <td class="num">{{ $pct($p['on_time_pct']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection

@section('definitions')
    @include('partials.report.definitions')
    <ul>
        <li><strong>Average duration</strong> is the time from the trip starting to the trip ending, averaged over the trips on that route.</li>
        <li><strong>Distance</strong> comes from the phone's GPS and is the sum over the trips that have a measured distance. Where some trips have none, the figure says how many it covers.</li>
        <li><strong>Not scored</strong> counts trips with no timetable plus unscheduled trips. They are left out of on-time figures.</li>
        <li><strong>Days and weeks</strong> are counted in {{ $timezone }} time. Weeks start on Monday. Days with no trips are not listed.</li>
    </ul>
@endsection
