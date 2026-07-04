@extends('layouts.app')

@section('title', 'Report')
@section('heading', 'Report detail')
@section('nav_reports', 'active')

@section('content')
    <div class="row">
        <div class="col-12 col-lg-8">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <span class="badge text-bg-light border">{{ $report->categoryLabel() }}</span>
                            <span class="badge {{ $report->statusBadge() }} ms-1">{{ $report->statusLabel() }}</span>
                        </div>
                        <a href="{{ route('reports.index') }}" class="btn btn-sm btn-light">
                            <i class="bi bi-arrow-left me-1"></i>Back
                        </a>
                    </div>

                    <p style="white-space: pre-wrap;">{{ $report->description }}</p>

                    <hr>

                    <dl class="row mb-0 small">
                        <dt class="col-sm-3">Reporter</dt>
                        <dd class="col-sm-9">{{ $report->reporter?->full_name ?? '—' }}</dd>

                        <dt class="col-sm-3">Submitted</dt>
                        <dd class="col-sm-9">{{ $report->created_at?->format('M j, Y g:i A') ?? '—' }}</dd>

                        @if ($report->vehicle)
                            <dt class="col-sm-3">Vehicle</dt>
                            <dd class="col-sm-9">{{ $report->vehicle->plate_number }}</dd>
                        @endif
                        @if ($report->route)
                            <dt class="col-sm-3">Route</dt>
                            <dd class="col-sm-9">{{ $report->route->code }} — {{ $report->route->name }}</dd>
                        @endif
                        @if ($report->trip_id)
                            <dt class="col-sm-3">Trip</dt>
                            <dd class="col-sm-9"><code>{{ $report->trip_id }}</code></dd>
                        @endif
                        @if ($orgName)
                            <dt class="col-sm-3">Organization</dt>
                            <dd class="col-sm-9">{{ $orgName }}</dd>
                        @endif
                        @if ($report->resolved_at)
                            <dt class="col-sm-3">Closed</dt>
                            <dd class="col-sm-9">
                                {{ $report->resolved_at->format('M j, Y g:i A') }}
                                by {{ $report->resolvedBy?->full_name ?? '—' }}
                            </dd>
                        @endif
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h2 class="h6 mb-3">Triage</h2>
                    <form method="POST" action="{{ route('reports.update', $report) }}">
                        @csrf
                        @method('PUT')
                        <div class="mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select name="status" id="status" class="form-select @error('status') is-invalid @enderror">
                                @foreach (\App\Models\Report::STATUSES as $value => $label)
                                    <option value="{{ $value }}" @selected($report->status === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <div class="form-text">Resolved / Dismissed records who closed it and when.</div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-check-lg me-1"></i>Update status
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
