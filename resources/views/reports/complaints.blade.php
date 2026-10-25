@extends('layouts.report')

@section('title', 'Complaints report')
@section('subtitle')
    {{ $from->format('M j, Y') }} &ndash; {{ $to->format('M j, Y') }} &middot; {{ $routeLabel }}
@endsection

@section('toolbar')
    <a class="btn" href="{{ route('report-center.complaints.export', $periodQuery) }}">Export CSV</a>
    <a class="btn" href="{{ route('reports.index') }}">Back to Reports</a>
@endsection

@php
    $duration = static function (?int $m): string {
        if ($m === null) return '—';
        if ($m < 60) return $m.' min';
        if ($m < 1440) return intdiv($m, 60).'h '.($m % 60).'m';
        return intdiv($m, 1440).'d '.intdiv($m % 1440, 60).'h';
    };
    $medianLabel = 'Median time to close';
    if ($figures['slowest_close_minutes'] !== null) {
        $medianLabel .= ' (slowest '.$duration($figures['slowest_close_minutes']).')';
    }
    $keyFigures = [
        ['label' => 'Reports received', 'value' => (string) $figures['received']],
        ['label' => 'Closed', 'value' => (string) $figures['closed']],
        ['label' => 'Still open', 'value' => (string) $figures['open']],
        ['label' => $medianLabel, 'value' => $duration($figures['median_close_minutes'])],
    ];
@endphp

@section('content')
    @include('partials.report.key-figures', ['keyFigures' => $keyFigures])

    @if ($figures['received'] === 0)
        <p class="empty">No data for this period.</p>
    @else
        <h2>By category</h2>
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
                @foreach ($byCategory as $c)
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

        <h2>By status and outcome</h2>
        <table>
            <thead>
                <tr>
                    <th>Status</th>
                    <th class="num">Reports</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($byStatus as $s)
                    <tr>
                        <td>{{ $s['label'] }}</td>
                        <td class="num">{{ $s['count'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <h2>By route</h2>
        <table>
            <thead>
                <tr>
                    <th>Route</th>
                    <th class="num">Received</th>
                    <th class="num">Closed</th>
                    <th class="num">Still open</th>
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
                        <td class="num">{{ $r['received'] }}</td>
                        <td class="num">{{ $r['closed'] }}</td>
                        <td class="num">{{ $r['open'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection

@section('definitions')
    <ul>
        <li><strong>Received</strong> counts the reports passengers filed during the period. A report is counted in the period it was filed, even if it was closed later.</li>
        <li><strong>Closed</strong> means resolved or dismissed. <strong>Still open</strong> means pending or under review.</li>
        <li><strong>Time to close</strong> runs from when the passenger filed the report to when an administrator closed it. The median is the middle value; the slowest is the longest. Both are dashes when nothing was closed.</li>
        <li><strong>A complaint is a report, not a finding.</strong> Only <em>upheld</em> reports were judged true. A report closed as resolved without a recorded outcome is shown as &ldquo;Outcome not recorded&rdquo; and is never counted as upheld.</li>
        <li><strong>Counts against a driver?</strong> Only reports about driver conduct count against a driver. Safety reports are shown separately because they could concern the driver, the vehicle or the road. Reports about the vehicle, the route or anything else are not about a driver.</li>
        <li><strong>Not linked to a route</strong> groups reports with no route recorded, or whose route no longer exists.</li>
        <li><strong>Privacy.</strong> This report contains counts only. It does not name any passenger, driver or vehicle and does not reproduce what passengers wrote.</li>
    </ul>
@endsection
