@php
    $c = $row['complaints'];
    $complaintLabels = [
        'received' => 'received', 'under_review' => 'under review', 'upheld' => 'upheld',
        'not_upheld' => 'not upheld', 'outcome_not_recorded' => 'outcome not recorded', 'dismissed' => 'dismissed',
    ];
@endphp
    <div class="card border-0 shadow-sm mb-3" id="complaints">
        <div class="card-body pb-0">
            <h3 class="h6 mb-1">{{ $complaintsTitle ?? 'Complaints behind the count' }}</h3>
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
