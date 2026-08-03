@extends('layouts.app')

@section('title', 'Reports')
@section('heading', 'Reports')
@section('nav_reports', 'active')

@section('content')
    <div class="page-toolbar d-flex flex-wrap gap-2 align-items-center">
        <a href="{{ route('reports.index') }}"
           class="btn btn-sm {{ $activeStatus ? 'btn-outline-secondary' : 'btn-primary' }}">
            All <span class="badge text-bg-light ms-1">{{ $counts->sum() }}</span>
        </a>
        @foreach (\App\Models\Report::STATUSES as $value => $label)
            <a href="{{ route('reports.index', ['status' => $value]) }}"
               class="btn btn-sm {{ $activeStatus === $value ? 'btn-primary' : 'btn-outline-secondary' }}">
                {{ $label }} <span class="badge text-bg-light ms-1">{{ $counts[$value] ?? 0 }}</span>
            </a>
        @endforeach
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Submitted</th>
                        <th>Reporter</th>
                        <th>Category</th>
                        <th>About</th>
                        <th>Details</th>
                        <th>Status</th>
                        @if ($ctx->isSuperAdmin())
                            <th>Organization</th>
                        @endif
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($reports as $report)
                        <tr>
                            <td class="text-nowrap">
                                {{ $report->created_at?->format('M j, Y') }}
                                <div class="text-muted small">{{ $report->created_at?->format('g:i A') }}</div>
                            </td>
                            <td>
                                @if ($report->is_anonymous)
                                    <span class="text-muted fst-italic"><i class="bi bi-incognito me-1"></i>Anonymous</span>
                                @else
                                    {{ $report->reporterDisplayName() ?? '—' }}
                                @endif
                            </td>
                            <td><span class="badge text-bg-light border">{{ $report->categoryLabel() }}</span></td>
                            <td class="text-nowrap">
                                @if ($report->vehicle)
                                    <span class="badge text-bg-light border"><i class="bi bi-bus-front me-1"></i>{{ $report->vehicle->plate_number }}</span>
                                @endif
                                @if ($report->route)
                                    <span class="badge text-bg-light border"><i class="bi bi-signpost-2 me-1"></i>{{ $report->route->code }}</span>
                                @endif
                                @if ($report->trip_id)
                                    <span class="badge text-bg-light border"><i class="bi bi-record-circle me-1"></i>trip</span>
                                @endif
                            </td>
                            <td class="text-muted">{{ Str::limit($report->description, 60) }}</td>
                            <td><span class="badge {{ $report->statusBadge() }}">{{ $report->statusLabel() }}</span></td>
                            @if ($ctx->isSuperAdmin())
                                <td>{{ $orgNames[$report->organization_id] ?? '—' }}</td>
                            @endif
                            <td class="text-end">
                                <a href="{{ route('reports.show', $report) }}" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-eye me-1"></i>View
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $ctx->isSuperAdmin() ? 8 : 7 }}" class="p-0">
                                <div class="empty-state text-center">
                                    <i class="bi bi-flag"></i>
                                    <p class="empty-state-title mb-0">
                                        No reports{{ $activeStatus ? ' marked “' . \App\Models\Report::STATUSES[$activeStatus] . '”' : ' yet' }}.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
