@php
    $punctuality = [
        'on_time' => ['On time', 'text-bg-success'], 'early' => ['Early', 'text-bg-info'],
        'late' => ['Late', 'text-bg-warning'], 'no_timetable' => ['No timetable', 'text-bg-light border'],
        'unscheduled' => ['Unscheduled', 'text-bg-light border'],
    ];
@endphp
    <div class="card border-0 shadow-sm">
        <div class="card-body pb-0"><h3 class="h6 mb-0">{{ $tripsTitle ?? 'Trips' }}</h3></div>
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
        @if ($trips instanceof \Illuminate\Contracts\Pagination\Paginator && $trips->hasPages())
            <div class="card-body">{{ $trips->links() }}</div>
        @endif
    </div>
