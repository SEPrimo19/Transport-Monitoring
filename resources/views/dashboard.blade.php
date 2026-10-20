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

    {{-- One box over all eight tenant screens. org_admin only, matching the gate on
         /search itself and on every screen it searches -- a super_admin has no
         cooperative to search, and the route would refuse them anyway.

         Submits rather than searching as you type: this reads eight tables, and a
         query per keystroke on a free-tier dyno would be slower AND noisier than one
         query when the admin has finished the thought. The per-screen boxes are the
         opposite, and instant, because each already has its whole table on the page. --}}
    @if ($ctx->isOrgAdmin())
        <form method="GET" action="{{ route('search') }}" class="mb-4" role="search">
            <div class="input-group input-group-lg shadow-sm">
                <span class="input-group-text bg-white border-end-0 text-muted">
                    <i class="bi bi-search"></i>
                </span>
                <input type="search" name="q"
                       class="form-control border-start-0 ps-0"
                       placeholder="Search vehicles, drivers, terminals, routes, schedules, assignments, trips, reports…"
                       aria-label="Search everything in your organization" autocomplete="off">
                <button class="btn btn-brand px-4" type="submit">
                    <span class="d-none d-sm-inline">Search</span>
                    <i class="bi bi-arrow-return-left d-sm-none"></i>
                </button>
            </div>
        </form>
    @endif

    {{-- Actionable status band. Both counts are platform health and fine for either
         tier, but Live Ops and Reports are tenant data behind the tenant.admin gate —
         a super_admin's link here would always 403. So the count stays for both
         tiers; only org_admin gets the link and the call-to-action wording. --}}
    <div class="row g-3 mb-1">
        <div class="col-12 col-md-6">
            @if ($ctx->isOrgAdmin())
            <a href="{{ route('operations.index') }}" class="text-decoration-none text-reset">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body d-flex align-items-center">
                        <span class="stat-icon stat-icon--brand me-3">
                            <i class="bi bi-broadcast fs-4"></i>
                        </span>
                        <div>
                            <div class="h3 mb-0 fw-semibold">{{ number_format($liveNow) }}</div>
                            <div class="text-muted small">{{ Str::plural('vehicle', $liveNow) }} transmitting now — open Live Ops</div>
                        </div>
                        <i class="bi bi-chevron-right ms-auto text-muted"></i>
                    </div>
                </div>
            </a>
            @else
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center">
                    <span class="stat-icon stat-icon--brand me-3">
                        <i class="bi bi-broadcast fs-4"></i>
                    </span>
                    <div>
                        <div class="h3 mb-0 fw-semibold">{{ number_format($liveNow) }}</div>
                        <div class="text-muted small">{{ Str::plural('vehicle', $liveNow) }} transmitting now</div>
                    </div>
                </div>
            </div>
            @endif
        </div>
        <div class="col-12 col-md-6">
            @if ($ctx->isOrgAdmin())
            <a href="{{ route('reports.index', ['status' => 'submitted']) }}" class="text-decoration-none text-reset">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body d-flex align-items-center">
                        <span class="stat-icon {{ $reportsPending > 0 ? 'stat-icon--warning' : 'stat-icon--muted' }} me-3">
                            <i class="bi bi-flag fs-4"></i>
                        </span>
                        <div>
                            <div class="h3 mb-0 fw-semibold">{{ number_format($reportsPending) }}</div>
                            <div class="text-muted small">{{ Str::plural('report', $reportsPending) }} awaiting review — triage now</div>
                        </div>
                        <i class="bi bi-chevron-right ms-auto text-muted"></i>
                    </div>
                </div>
            </a>
            @else
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center">
                    <span class="stat-icon {{ $reportsPending > 0 ? 'stat-icon--warning' : 'stat-icon--muted' }} me-3">
                        <i class="bi bi-flag fs-4"></i>
                    </span>
                    <div>
                        <div class="h3 mb-0 fw-semibold">{{ number_format($reportsPending) }}</div>
                        <div class="text-muted small">{{ Str::plural('report', $reportsPending) }} awaiting review</div>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>

    {{-- KPI tiles --}}
    <div class="row g-3 mt-0 mb-3">
        @foreach ($tiles as $t)
            <div class="col-6 col-md-4 col-xl-3">
                {{-- The count is platform health for either tier; the href (and thus the
                     click-through) is tenant data behind tenant.admin, so it is only
                     present at all for org_admin — see DashboardController::index(). --}}
                @if (! empty($t['href']))
                <a href="{{ $t['href'] }}" class="text-decoration-none text-reset">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="text-muted small text-uppercase">{{ $t['label'] }}</div>
                                    <div class="display-6 fw-semibold">{{ number_format($t['value']) }}</div>
                                </div>
                                <span class="stat-icon stat-icon--muted stat-icon--sm">
                                    <i class="bi bi-{{ $t['icon'] }} fs-5"></i>
                                </span>
                            </div>
                            <div class="small text-muted mt-1">{{ $t['sub'] }}</div>
                        </div>
                    </div>
                </a>
                @else
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="text-muted small text-uppercase">{{ $t['label'] }}</div>
                                <div class="display-6 fw-semibold">{{ number_format($t['value']) }}</div>
                            </div>
                            <span class="stat-icon stat-icon--muted stat-icon--sm">
                                <i class="bi bi-{{ $t['icon'] }} fs-5"></i>
                            </span>
                        </div>
                        <div class="small text-muted mt-1">{{ $t['sub'] }}</div>
                    </div>
                </div>
                @endif
            </div>
        @endforeach
    </div>

    <div class="row g-3">
        {{-- Recent reports. Tenant data, so a platform admin does not see this card at
             all: the controller sends an empty collection and the link would 403 anyway. --}}
        @if ($ctx->isOrgAdmin())
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
                                {{-- reporterDisplayName(), never reporter->full_name: a report
                                     filed anonymously must not name the passenger here either.
                                     Matches reports/index and reports/show. --}}
                                @if ($r->is_anonymous)
                                    <span class="fst-italic"><i class="bi bi-incognito me-1"></i>Anonymous</span>
                                @else
                                    {{ $r->reporterDisplayName() ?? 'Passenger' }}
                                @endif
                                @if ($r->route_code) · {{ $r->route_code }} @endif
                                @if ($r->vehicle_plate) · {{ $r->vehicle_plate }} @endif
                                · {{ $r->created_at?->diffForHumans() }}
                            </div>
                        </a>
                    @empty
                        <div class="list-group-item text-muted text-center py-4">No reports yet.</div>
                    @endforelse
                </div>
            </div>
        </div>
        @endif

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
                    {{-- Tenant data behind tenant.admin — same reasoning as the KPI tiles above. --}}
                    @if ($ctx->isOrgAdmin())
                    <a href="{{ route('vehicles.index') }}" class="small d-inline-block mt-3">Manage vehicles</a>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
