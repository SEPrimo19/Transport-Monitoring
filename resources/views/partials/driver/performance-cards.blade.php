@php $c = $row['complaints']; @endphp
    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100"><div class="card-body">
                <div class="text-muted small">Trips</div>
                <div class="h4 mb-0">{{ $row['trips'] }}</div>
                <div class="small text-muted">{{ $row['hours'] }} h &middot; {{ $row['distance_km'] !== null ? $row['distance_km'].' km' : 'distance unknown' }}</div>
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
