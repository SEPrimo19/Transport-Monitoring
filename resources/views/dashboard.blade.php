@extends('layouts.app')

@section('title', 'Dashboard')
@section('heading', 'Dashboard')
@section('nav_dashboard', 'active')

@section('content')
    <div class="mb-4">
        <h2 class="h5 mb-1">Welcome back, {{ $ctx->fullName ?? $ctx->email }}</h2>
        <p class="text-muted mb-0 small">
            Signed in as <span class="badge text-bg-secondary">{{ $ctx->role }}</span>
            @if ($ctx->isSuperAdmin())
                — platform-wide view across <strong>all</strong> organizations.
            @else
                — scoped to <strong>{{ $orgName ?? 'your organization' }}</strong>.
            @endif
        </p>
    </div>

    {{-- Actionable status band --}}
    <div class="row g-3 mb-1">
        <div class="col-12 col-md-6">
            <a href="{{ route('operations.index') }}" class="text-decoration-none text-reset">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body d-flex align-items-center">
                        <span class="rounded-circle d-inline-flex align-items-center justify-content-center me-3"
                              style="width:3rem;height:3rem;background:rgba(25,135,84,.12)">
                            <i class="bi bi-broadcast fs-4 text-success"></i>
                        </span>
                        <div>
                            <div class="h3 mb-0 fw-semibold">{{ number_format($liveNow) }}</div>
                            <div class="text-muted small">{{ Str::plural('vehicle', $liveNow) }} transmitting now — open Live Ops</div>
                        </div>
                        <i class="bi bi-chevron-right ms-auto text-muted"></i>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-12 col-md-6">
            <a href="{{ route('reports.index', ['status' => 'submitted']) }}" class="text-decoration-none text-reset">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body d-flex align-items-center">
                        <span class="rounded-circle d-inline-flex align-items-center justify-content-center me-3"
                              style="width:3rem;height:3rem;background:{{ $reportsPending > 0 ? 'rgba(255,193,7,.18)' : 'rgba(108,117,125,.12)' }}">
                            <i class="bi bi-flag fs-4 {{ $reportsPending > 0 ? 'text-warning' : 'text-secondary' }}"></i>
                        </span>
                        <div>
                            <div class="h3 mb-0 fw-semibold">{{ number_format($reportsPending) }}</div>
                            <div class="text-muted small">{{ Str::plural('report', $reportsPending) }} awaiting review — triage now</div>
                        </div>
                        <i class="bi bi-chevron-right ms-auto text-muted"></i>
                    </div>
                </div>
            </a>
        </div>
    </div>

    {{-- KPI tiles --}}
    <div class="row g-3 mt-0 mb-3">
        @foreach ($tiles as $t)
            <div class="col-6 col-md-4 col-xl-3">
                <a href="{{ $t['href'] }}" class="text-decoration-none text-reset">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="text-muted small text-uppercase">{{ $t['label'] }}</div>
                                    <div class="display-6 fw-semibold">{{ number_format($t['value']) }}</div>
                                </div>
                                <i class="bi bi-{{ $t['icon'] }} fs-3 text-secondary"></i>
                            </div>
                            <div class="small text-muted mt-1">{{ $t['sub'] }}</div>
                        </div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>

    <div class="row g-3">
        {{-- Recent reports --}}
        <div class="col-12 col-lg-7">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body pb-0 d-flex justify-content-between align-items-center">
                    <h2 class="h6 mb-0">Recent reports</h2>
                    <a href="{{ route('reports.index') }}" class="small">View all</a>
                </div>
                <div class="list-group list-group-flush mt-2">
                    @forelse ($recentReports as $r)
                        <a href="{{ route('reports.show', $r) }}" class="list-group-item list-group-item-action">
                            <div class="d-flex justify-content-between align-items-start">
                                <span class="fw-semibold">{{ $r->categoryLabel() }}</span>
                                <span class="badge {{ $r->statusBadge() }}">{{ $r->statusLabel() }}</span>
                            </div>
                            <div class="small text-muted text-truncate">
                                {{ $r->reporter?->full_name ?? 'Passenger' }}
                                @if ($r->route) · {{ $r->route->code }} @endif
                                @if ($r->vehicle) · {{ $r->vehicle->plate_number }} @endif
                                · {{ $r->created_at?->diffForHumans() }}
                            </div>
                        </a>
                    @empty
                        <div class="list-group-item text-muted text-center py-4">No reports yet.</div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Fleet status --}}
        <div class="col-12 col-lg-5">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h6 mb-3">Fleet status</h2>
                    @php $vTotal = max(1, $vehicleStatus->sum()); @endphp
                    <div class="progress" role="progressbar" aria-label="Vehicles by status" style="height:1.25rem;">
                        @foreach (['active' => 'success', 'maintenance' => 'warning', 'inactive' => 'secondary'] as $st => $cls)
                            @php $n = (int) ($vehicleStatus[$st] ?? 0); @endphp
                            @if ($n > 0)
                                <div class="progress-bar bg-{{ $cls }}" style="width: {{ round($n / $vTotal * 100) }}%"
                                     title="{{ ucfirst($st) }}: {{ $n }}"></div>
                            @endif
                        @endforeach
                    </div>
                    <div class="d-flex flex-column gap-2 small mt-3">
                        @foreach (['active' => 'success', 'maintenance' => 'warning', 'inactive' => 'secondary'] as $st => $cls)
                            <div class="d-flex align-items-center justify-content-between">
                                <span>
                                    <span class="d-inline-block rounded-circle me-2 bg-{{ $cls }}" style="width:.7rem;height:.7rem;"></span>
                                    {{ ucfirst($st) }}
                                </span>
                                <span class="fw-semibold">{{ (int) ($vehicleStatus[$st] ?? 0) }}</span>
                            </div>
                        @endforeach
                    </div>
                    <a href="{{ route('vehicles.index') }}" class="small d-inline-block mt-3">Manage vehicles</a>
                </div>
            </div>
        </div>
    </div>
@endsection
