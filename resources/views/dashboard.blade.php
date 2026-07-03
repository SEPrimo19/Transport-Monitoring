@extends('layouts.app')

@section('title', 'Dashboard')
@section('heading', 'Dashboard')
@section('nav_dashboard', 'active')

@section('content')
    <div class="mb-4">
        <h2 class="h5">Welcome back, {{ $ctx->fullName ?? $ctx->email }}</h2>
        <p class="text-muted mb-0">
            Signed in as <span class="badge text-bg-primary">{{ $ctx->role }}</span>
            @if ($ctx->isSuperAdmin())
                &mdash; you can see data across <strong>all</strong> organizations.
            @else
                &mdash; scoped to <strong>your organization</strong>.
            @endif
        </p>
    </div>

    <div class="row g-3">
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-muted small text-uppercase">Vehicles</div>
                            <div class="display-6 fw-semibold">{{ number_format($stats['vehicles']) }}</div>
                        </div>
                        <i class="bi bi-bus-front fs-2 text-primary"></i>
                    </div>
                    <div class="small text-muted mt-2">
                        {{ $ctx->isSuperAdmin() ? 'Across all organizations' : 'In your fleet' }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="alert alert-light border mt-4 mb-0 small text-muted">
        <i class="bi bi-info-circle me-1"></i>
        The greyed-out sidebar items (@if ($ctx->isSuperAdmin())Organizations, @endif Vehicles, Drivers, Routes, Reports)
        are the next screens to build. This shell plus the tenant-scoped count above confirm authentication and
        organization isolation are working end-to-end.
    </div>
@endsection
