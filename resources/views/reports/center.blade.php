@extends('layouts.app')

@section('title', 'Report center')
@section('heading', 'Report center')
@section('nav_report_center', 'active')

@php
    $activePreset = ($preset !== null && in_array((int) $preset, \App\Support\ScorecardPolicy::PRESETS, true))
        ? (int) $preset : null;
@endphp

@section('content')
    <div class="page-toolbar">
        <p class="text-muted mb-0">
            Choose a period once, then generate any report for it. Each report opens as a clean page you can
            print or save as a PDF, and most can also be exported as a spreadsheet.
        </p>
    </div>

    @if (! empty($notice))
        <div class="alert alert-warning py-2 small" role="status">{{ $notice }}</div>
    @endif

    <form method="GET" action="{{ route('report-center.index') }}" class="row g-2 align-items-end mb-4">
        <div class="col-auto">
            <div class="btn-group" role="group" aria-label="Period">
                @foreach (\App\Support\ScorecardPolicy::PRESETS as $days)
                    <a href="{{ route('report-center.index', array_filter(['preset' => $days, 'route' => $routeId])) }}"
                       class="btn btn-sm {{ ($activePreset === $days || ($activePreset === null && $days === 30 && ! request()->has('from'))) ? 'btn-primary' : 'btn-outline-secondary' }}">
                        {{ $days }} days
                    </a>
                @endforeach
            </div>
        </div>
        <div class="col-auto">
            <label class="form-label small mb-0" for="from">From</label>
            <input type="date" class="form-control form-control-sm" id="from" name="from" value="{{ $from->format('Y-m-d') }}">
        </div>
        <div class="col-auto">
            <label class="form-label small mb-0" for="to">To</label>
            <input type="date" class="form-control form-control-sm" id="to" name="to" value="{{ $to->format('Y-m-d') }}">
        </div>
        <div class="col-auto">
            <label class="form-label small mb-0" for="route">Route</label>
            <select class="form-select form-select-sm" id="route" name="route">
                <option value="">All routes</option>
                @foreach ($routes as $r)
                    <option value="{{ $r->id }}" @selected($routeId === $r->id)>{{ $r->code }} &middot; {{ $r->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-sm btn-brand">Apply</button>
        </div>
    </form>

    <div class="row g-3">
        <div class="col-12 col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h6 mb-1">Trips</h2>
                    <p class="text-muted small mb-3">
                        How many trips ran, how long they took and how many left on time, by route and by day or week.
                    </p>
                    <a class="btn btn-sm btn-brand" href="{{ route('report-center.trips', $periodQuery) }}">
                        <i class="bi bi-file-earmark-text me-1"></i>Generate report
                    </a>
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('report-center.trips.export', $periodQuery) }}">
                        <i class="bi bi-filetype-csv me-1"></i>Export CSV
                    </a>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h6 mb-1">Driver performance</h2>
                    <p class="text-muted small mb-3">
                        How each driver's trips went, on time and by route, and what passengers reported
                        about driver conduct. No scores and no ranking.
                    </p>
                    <a class="btn btn-sm btn-brand" href="{{ route('report-center.drivers', $periodQuery) }}">
                        <i class="bi bi-file-earmark-text me-1"></i>Generate report
                    </a>
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('analytics.drivers.export', $periodQuery) }}">
                        <i class="bi bi-filetype-csv me-1"></i>Export CSV
                    </a>
                </div>
            </div>
        </div>
    </div>
@endsection
