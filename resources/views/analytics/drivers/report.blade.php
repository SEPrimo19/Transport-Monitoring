@extends('layouts.report')

@section('title', 'Driver performance')
@section('subtitle')
    {{ $from->format('M j, Y') }} &ndash; {{ $to->format('M j, Y') }} &middot; {{ $routeLabel }}
@endsection

@section('toolbar')
    <a class="btn" href="{{ route('analytics.drivers.export', $periodQuery) }}">Export CSV</a>
    <a class="btn" href="{{ route('analytics.drivers.index', $periodQuery) }}">Back to the screen</a>
@endsection

@php
    $pct = static fn ($v) => $v === null ? '—' : rtrim(rtrim(number_format($v, 1), '0'), '.').'%';
    $keyFigures = [
        ['label' => 'Drivers with trips', 'value' => (string) $figures['drivers_with_trips']],
        ['label' => 'Trips', 'value' => (string) $figures['trips']],
        ['label' => 'On time (of scored trips)', 'value' => $pct($figures['on_time_pct'])],
        ['label' => 'Complaints received', 'value' => (string) $figures['complaints']],
    ];
@endphp

@section('content')
    @include('partials.report.key-figures', ['keyFigures' => $keyFigures])

    @if ($figures['trips'] === 0)
        <p class="empty">No data for this period.</p>
    @else
        <h2>Drivers</h2>
        <table>
            <thead>
                <tr>
                    <th>Driver</th>
                    <th class="num">Trips</th>
                    <th class="num">On time</th>
                    <th class="num">Early / late</th>
                    <th class="num">Avg speed</th>
                    <th class="num">Over-speed</th>
                    <th class="num">Complaints</th>
                    <th class="num">Safety</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td>
                            {{ $row['name'] }}
                            @if ($row['employee_code'] && $row['employee_code'] !== $row['name'])
                                <div class="sub">{{ $row['employee_code'] }}</div>
                            @endif
                        </td>
                        <td class="num">
                            @if ($row['trips'] === 0)
                                <span class="sub">no trips</span>
                            @else
                                {{ $row['trips'] }}
                                <div class="sub">{{ $row['hours'] }} h &middot; {{ $row['distance_km'] !== null ? $row['distance_km'].' km' : 'distance unknown' }}</div>
                            @endif
                        </td>
                        <td class="num">
                            @if ($row['on_time_pct'] !== null)
                                {{ $pct($row['on_time_pct']) }}
                                <div class="sub">of {{ $row['scored'] }} scored</div>
                            @else
                                —
                                @if ($row['trips'] > 0)
                                    <div class="sub">not scored</div>
                                @endif
                            @endif
                        </td>
                        <td class="num">{{ $row['early'] }} / {{ $row['late'] }}</td>
                        <td class="num">
                            @if ($row['avg_speed_kmh'] !== null)
                                {{ round($row['avg_speed_kmh']) }} km/h
                                <div class="sub">top {{ round($row['max_speed_kmh']) }}</div>
                            @else
                                —
                            @endif
                        </td>
                        <td class="num">
                            @if ($overspeedLimit === null)
                                <span class="sub">limit not set</span>
                            @elseif ($row['overspeed_share'] !== null)
                                {{ round($row['overspeed_share'] * 100) }}%
                                <div class="sub">over {{ $overspeedLimit }} km/h</div>
                            @else
                                —
                            @endif
                        </td>
                        <td class="num">
                            {{ $row['complaints']['total'] }}
                            @if ($row['rate_per_100'] !== null)
                                <div class="sub">{{ $row['rate_per_100'] }} per 100 trips</div>
                            @elseif ($row['trips'] > 0 && $row['complaints']['total'] > 0)
                                <div class="sub">too few trips to compare</div>
                            @endif
                        </td>
                        <td class="num">{{ $row['safety_related'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <h2>By route</h2>
        <table>
            <thead>
                <tr>
                    <th>Driver</th>
                    <th>Route</th>
                    <th class="num">Trips</th>
                    <th class="num">On time</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($perRoute as $r)
                    <tr>
                        <td>
                            {{ $r['name'] }}
                            @if ($r['employee_code'] && $r['employee_code'] !== $r['name'])
                                <div class="sub">{{ $r['employee_code'] }}</div>
                            @endif
                        </td>
                        <td>{{ $r['route_code'] }}</td>
                        <td class="num">{{ $r['trips'] }}</td>
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
        <li><strong>Speed and over-speed</strong> come from phone GPS read about once a minute, so they are indicative, not evidence. Over-speed is the share of readings above the cooperative's limit, and is shown only when a limit is set.</li>
        <li><strong>Complaints.</strong> A complaint is a passenger's report, not a finding. Only reports about driver conduct count against a driver, and only <em>upheld</em> ones were judged true. Safety reports are counted separately; reports about the vehicle or the route are not counted against the driver.</li>
        <li><strong>Rate per 100 trips</strong> is shown only from {{ $minTripsForRate }} trips, so a small sample does not look like a pattern.</li>
        <li><strong>No ranking.</strong> Drivers are listed alphabetically. There is no score and no league table.</li>
    </ul>
    @if ($unattributed > 0)
        <p>{{ $unattributed }} conduct {{ \Illuminate\Support\Str::plural('report', $unattributed) }} in this period named no trip, so {{ $unattributed === 1 ? 'it is' : 'they are' }} not counted against any driver.</p>
    @endif
@endsection
