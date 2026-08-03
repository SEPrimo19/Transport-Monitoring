@extends('layouts.app')

@section('title', 'Report')
@section('heading', 'Report detail')
@section('nav_reports', 'active')

@section('content')
    <div class="row">
        <div class="col-12 col-lg-8">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start pb-3 mb-3 border-bottom">
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
                        <dd class="col-sm-9">
                            @if ($report->is_anonymous)
                                <span class="text-muted fst-italic"><i class="bi bi-incognito me-1"></i>Anonymous</span>
                                <span class="badge text-bg-light border ms-1">identity hidden by reporter</span>
                            @else
                                {{ $report->reporterDisplayName() ?? '—' }}
                            @endif
                        </dd>

                        <dt class="col-sm-3">Submitted</dt>
                        <dd class="col-sm-9">{{ $report->created_at?->format('M j, Y g:i A') ?? '—' }}</dd>

                        @if ($report->hasPassengerLocation())
                            <dt class="col-sm-3">Reported from</dt>
                            <dd class="col-sm-9">
                                {{ number_format($report->passenger_latitude, 5) }}, {{ number_format($report->passenger_longitude, 5) }}
                                <a href="https://www.openstreetmap.org/?mlat={{ $report->passenger_latitude }}&mlon={{ $report->passenger_longitude }}#map=16/{{ $report->passenger_latitude }}/{{ $report->passenger_longitude }}"
                                   target="_blank" rel="noopener" class="ms-1">view on map</a>
                            </dd>
                        @endif

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

            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <h2 class="h6 mb-3"><i class="bi bi-paperclip me-1"></i>Evidence</h2>
                    @if (! $report->hasEvidence())
                        <p class="text-muted mb-0">No evidence was attached to this report.</p>
                    @elseif ($evidenceUrl)
                        @if ($report->evidenceIsImage())
                            <a href="{{ $evidenceUrl }}" target="_blank" rel="noopener">
                                <img src="{{ $evidenceUrl }}" alt="Report evidence"
                                     class="img-fluid rounded border" style="max-height: 460px;">
                            </a>
                        @elseif ($report->evidenceIsVideo())
                            <video controls class="w-100 rounded border" style="max-height: 460px;">
                                <source src="{{ $evidenceUrl }}" type="{{ $report->evidence_mime_type }}">
                                Your browser can’t play this video — use Download below.
                            </video>
                        @else
                            <p class="text-muted">Attachment of type <code>{{ $report->evidence_mime_type }}</code>.</p>
                        @endif

                        <div class="d-flex align-items-center gap-2 mt-2 small">
                            <i class="bi bi-file-earmark text-muted"></i>
                            <span class="text-muted text-truncate">{{ $report->evidence_file_name ?? 'attachment' }}</span>
                            <span class="badge text-bg-light border">{{ $report->evidence_mime_type }}</span>
                            <a href="{{ $evidenceUrl }}" target="_blank" rel="noopener" download
                               class="ms-auto btn btn-sm btn-outline-secondary">
                                <i class="bi bi-download me-1"></i>Download
                            </a>
                        </div>
                        <p class="text-muted small mt-1 mb-0">
                            <i class="bi bi-shield-lock me-1"></i>Private file — this secure link expires in about an hour.
                        </p>
                    @else
                        <p class="mb-1">
                            <i class="bi bi-exclamation-triangle text-warning-emphasis me-1"></i>Couldn’t load the attachment right now.
                        </p>
                        <p class="text-muted small mb-0">
                            File: {{ $report->evidence_file_name ?? '—' }}
                            @if ($report->evidence_mime_type)(<code>{{ $report->evidence_mime_type }}</code>)@endif.
                            Your session may have expired — reload the page or sign in again.
                        </p>
                    @endif
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
