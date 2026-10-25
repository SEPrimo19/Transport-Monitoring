@extends('layouts.app')

@section('title', $name.' · Driver profile')
@section('heading', 'Driver profile')
@section('nav_drivers', 'active')

@php
    $statusClass = [
        'Active now' => 'text-bg-success', 'Upcoming' => 'text-bg-info',
        'Ended' => 'text-bg-secondary', 'Disabled' => 'text-bg-light border',
    ];
    $activePreset = ($preset ?? null) !== null && in_array((int) $preset, \App\Support\ScorecardPolicy::PRESETS, true)
        ? (int) $preset : null;
    $pid = $driver->profile_id;
@endphp

@section('content')
    <div class="page-toolbar d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div class="min-w-0">
            <a href="{{ route('drivers.index') }}" class="small text-decoration-none">
                <i class="bi bi-arrow-left me-1"></i>All drivers
            </a>
            <h2 class="h4 mb-0 mt-1 text-truncate">
                {{ $name }}
                <span class="badge {{ $driver->statusBadge() }} align-middle fs-6">{{ $driver->statusLabel() }}</span>
            </h2>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('drivers.edit', $driver) }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-pencil me-1"></i>Edit
            </a>
            <a href="{{ route('drivers.edit', $driver) }}#reset-password" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-key me-1"></i>Reset password
            </a>
            <a href="{{ route('analytics.drivers.show', ['driver' => $pid] + $periodQuery) }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-graph-up me-1"></i>Full performance detail
            </a>
            <a href="{{ route('report-center.drivers', ['driver' => $pid] + $periodQuery) }}" class="btn btn-sm btn-brand">
                <i class="bi bi-file-earmark-text me-1"></i>Generate report
            </a>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <h3 class="h6 mb-3">Profile</h3>
            <dl class="row mb-0">
                <dt class="col-sm-3 text-muted fw-normal">Signs in with</dt>
                <dd class="col-sm-9">
                    @if ($signIn['kind'] === 'username')
                        <span class="fw-semibold">{{ $signIn['value'] ?? '—' }}</span>
                        <span class="badge text-bg-light border ms-1">username</span>
                    @elseif ($signIn['kind'] === 'email')
                        {{ $signIn['value'] }}
                    @else
                        &mdash;
                    @endif
                </dd>
                <dt class="col-sm-3 text-muted fw-normal">Employee code</dt>
                <dd class="col-sm-9">{{ ($driver->employee_code ?? '') !== '' ? $driver->employee_code : '—' }}</dd>
                <dt class="col-sm-3 text-muted fw-normal">License number</dt>
                <dd class="col-sm-9">{{ ($driver->license_number ?? '') !== '' ? $driver->license_number : '—' }}</dd>
                <dt class="col-sm-3 text-muted fw-normal">Phone</dt>
                <dd class="col-sm-9 mb-0">{{ ($driver->phone ?? '') !== '' ? $driver->phone : '—' }}</dd>
            </dl>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body pb-0"><h3 class="h6 mb-0">Assignments</h3></div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light"><tr>
                    <th>Vehicle</th><th>Route</th><th>Shift starts</th><th>Shift ends</th><th>Status</th>
                </tr></thead>
                <tbody>
                    @forelse ($assignments as $a)
                        <tr>
                            <td>
                                {{ $a['vehicle'] }}
                                @if ($a['plate'] !== '')
                                    <div class="small text-muted">{{ $a['plate'] }}</div>
                                @endif
                            </td>
                            <td>{{ $a['route'] }}</td>
                            <td class="text-nowrap">{{ $a['starts_at']?->format('M j, Y g:i A') ?? '—' }}</td>
                            <td class="text-nowrap">{{ $a['ends_at']?->format('M j, Y g:i A') ?? 'open-ended' }}</td>
                            <td><span class="badge {{ $statusClass[$a['status']] ?? 'text-bg-light border' }}">{{ $a['status'] }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">No assignments.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
        <h3 class="h6 mb-0 me-2">Performance</h3>
        <span class="text-muted small">{{ $from->format('M j, Y') }} &ndash; {{ $to->format('M j, Y') }}</span>
        <div class="btn-group ms-auto" role="group" aria-label="Period">
            @foreach (\App\Support\ScorecardPolicy::PRESETS as $days)
                <a href="{{ route('drivers.show', array_filter(['driver' => $pid, 'preset' => $days, 'route' => $routeId])) }}"
                   class="btn btn-sm {{ ($activePreset === $days || ($activePreset === null && $days === 30 && ! request()->has('from'))) ? 'btn-primary' : 'btn-outline-secondary' }}">
                    {{ $days }} days
                </a>
            @endforeach
        </div>
    </div>

    @if (! empty($notice))
        <div class="alert alert-warning py-2 small" role="status">{{ $notice }}</div>
    @endif

    @include('partials.driver.performance-cards', ['row' => $row, 'overspeedLimit' => $overspeedLimit])

    @include('partials.driver.trips', ['trips' => $trips, 'tripsTitle' => 'Latest trips'])

    <div class="mt-3">
        @include('partials.driver.complaints', ['row' => $row, 'complaints' => $complaints, 'complaintsTitle' => 'Reports about conduct and safety'])
    </div>
@endsection
