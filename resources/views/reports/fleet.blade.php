@extends('layouts.report')

@section('title', 'Vehicles and routes report')
@section('subtitle')
    {{ $from->format('M j, Y') }} &ndash; {{ $to->format('M j, Y') }} &middot; {{ $routeLabel }}
@endsection

@section('toolbar')
    <a class="btn" href="{{ route('report-center.fleet.export', $periodQuery) }}">Export CSV</a>
    <a class="btn" href="{{ route('vehicles.index') }}">Back to Vehicles</a>
@endsection

@php
    $pct = static fn ($v) => $v === null ? '—' : rtrim(rtrim(number_format($v, 1), '0'), '.').'%';
    $duration = static function (?int $m): string {
        if ($m === null) return '—';
        return $m < 60 ? $m.' min' : intdiv($m, 60).'h '.($m % 60).'m';
    };
    $signed = static function (?int $d): string {
        if ($d === null) return '—';
        return ($d > 0 ? '+' : '').$d.' min';
    };
    $keyFigures = [
        ['label' => 'Trips', 'value' => (string) $figures['trips']],
        ['label' => 'Vehicles with trips', 'value' => (string) $figures['vehicles_with_trips']],
        ['label' => 'Idle vehicles', 'value' => $routeFiltered ? '—' : (string) $figures['idle_vehicles']],
        ['label' => 'Routes run', 'value' => (string) $figures['routes_run']],
    ];
@endphp

@section('content')
    @include('partials.report.key-figures', ['keyFigures' => $keyFigures])

    @if ($figures['trips'] === 0)
        <p class="empty">No data for this period.</p>
    @else
        <h2>By vehicle</h2>
        <table>
            <thead>
                <tr>
                    <th>Vehicle</th>
                    <th class="num">Trips</th>
                    <th class="num">Hours in service</th>
                    <th class="num">Distance</th>
                    <th class="num">On time</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($vehicles as $v)
                    <tr>
                        <td>
                            {{ $v['label'] }}
                            @if ($v['plate'] !== '')
                                <div class="sub">{{ $v['plate'] }}</div>
                            @endif
                        </td>
                        <td class="num">{{ $v['trips'] }}</td>
                        <td class="num">{{ $v['hours'] }} h</td>
                        <td class="num">
                            {{ $v['distance_km'] === null ? '—' : $v['distance_km'].' km' }}
                            @if ($v['distance_km'] !== null && $v['distance_trips'] < $v['trips'])
                                <div class="sub">from {{ $v['distance_trips'] }} of {{ $v['trips'] }} trips</div>
                            @endif
                        </td>
                        <td class="num">{{ $pct($v['on_time_pct']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>Idle vehicles</h2>
    @if ($routeFiltered)
        <p class="sub">Idle vehicles are not shown for a single route, because a vehicle with no trips on one route may have been busy on another. Choose all routes to see them.</p>
    @elseif ($idle === [])
        <p class="sub">None. Every active vehicle ran at least one trip in this period.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Vehicle</th>
                    <th class="num">Trips</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($idle as $i)
                    <tr>
                        <td>
                            {{ $i['label'] }}
                            @if ($i['plate'] !== '')
                                <div class="sub">{{ $i['plate'] }}</div>
                            @endif
                        </td>
                        <td class="num">0</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if ($figures['trips'] > 0)
        <h2>By route</h2>
        <table>
            <thead>
                <tr>
                    <th>Route</th>
                    <th class="num">Trips</th>
                    <th class="num">Average duration</th>
                    <th class="num">Scheduled duration</th>
                    <th class="num">Difference</th>
                    <th class="num">On time</th>
                    <th class="num">Not scored</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($routes as $r)
                    <tr>
                        <td>
                            {{ $r['route_code'] }}
                            @if ($r['route_name'] !== '')
                                <div class="sub">{{ $r['route_name'] }}</div>
                            @endif
                        </td>
                        <td class="num">{{ $r['trips'] }}</td>
                        <td class="num">{{ $duration($r['avg_duration_min']) }}</td>
                        <td class="num">{{ $duration($r['stored_duration_min']) }}</td>
                        <td class="num">{{ $signed($r['difference_min']) }}</td>
                        <td class="num">
                            {{ $pct($r['on_time_pct']) }}
                            @if ($r['on_time_pct'] !== null)
                                <div class="sub">of {{ $r['scored'] }} scored</div>
                            @endif
                        </td>
                        <td class="num">
                            {{ $r['no_timetable'] + $r['unscheduled'] }}
                            @if ($r['no_timetable'] + $r['unscheduled'] > 0)
                                <div class="sub">{{ $r['no_timetable'] }} no timetable &middot; {{ $r['unscheduled'] }} unscheduled</div>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection

@section('definitions')
    @include('partials.report.definitions')
    <ul>
        <li><strong>Vehicle</strong> is shown by its fleet code, or by its plate when it has no fleet code. The plate is repeated underneath to tell vehicles with the same code apart.</li>
        <li><strong>Idle</strong> means an active vehicle that ran no trip in this period. A vehicle in maintenance or retired is expected to be still and is not listed. Vehicles that are no longer on file are combined into one row.</li>
        <li><strong>Hours in service</strong> adds up the time from each trip starting to ending. <strong>Distance</strong> comes from the phone's GPS and adds up only the trips that have one; where some trips have none, the figure says how many it covers.</li>
        <li><strong>Average duration</strong> is the average time of the trips run on the route. <strong>Scheduled duration</strong> is the duration configured for the route. <strong>Difference</strong> is average minus scheduled: a plus sign means trips took longer than scheduled. A dash means one of the two is not known.</li>
        <li><strong>Not scored</strong> counts trips with no timetable plus unscheduled trips. A route with many of them is a route whose timetable is missing or out of date.</li>
        <li><strong>Vehicles with trips</strong> and <strong>Routes run</strong> count vehicles and routes that are on file; trips on vehicles or routes no longer on file are in the trip total.</li>
        <li><strong>No drivers.</strong> This report names vehicles and routes only. Days are counted in {{ $timezone }} time.</li>
    </ul>
@endsection
