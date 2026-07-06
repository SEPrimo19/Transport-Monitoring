@extends('layouts.app')

@section('title', 'Assignments')
@section('heading', 'Assignments')
@section('nav_assignments', 'active')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <p class="text-muted mb-0">
            {{ $assignments->count() }} {{ Str::plural('assignment', $assignments->count()) }}
            {{ $ctx->isSuperAdmin() ? 'across all organizations' : 'in your organization' }}
        </p>
        <a href="{{ route('assignments.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>Add assignment
        </a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Driver</th>
                        <th>Vehicle</th>
                        <th>Route</th>
                        <th>Valid window</th>
                        <th>Status</th>
                        @if ($ctx->isSuperAdmin())
                            <th>Organization</th>
                        @endif
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($assignments as $a)
                        <tr>
                            <td class="fw-semibold">
                                {{ $a->driver?->profile?->full_name ?? '—' }}
                                @if ($a->driver?->employee_code)
                                    <span class="text-muted small">({{ $a->driver->employee_code }})</span>
                                @endif
                            </td>
                            <td>
                                {{ $a->vehicle?->plate_number ?? '—' }}
                                @if ($a->vehicle?->fleet_code)
                                    <span class="text-muted small">{{ $a->vehicle->fleet_code }}</span>
                                @endif
                            </td>
                            <td><span class="text-muted">{{ $a->route?->code ?? '—' }}</span></td>
                            <td class="small text-muted">
                                {{ optional($a->starts_at)->format('M j, Y') ?? '—' }}
                                <i class="bi bi-arrow-right mx-1"></i>
                                {{ optional($a->ends_at)->format('M j, Y') ?? '—' }}
                            </td>
                            <td><span class="badge {{ $a->statusBadge() }}">{{ $a->is_active ? 'Active' : 'Inactive' }}</span></td>
                            @if ($ctx->isSuperAdmin())
                                <td>{{ $orgNames[$a->organization_id] ?? '—' }}</td>
                            @endif
                            <td class="text-end text-nowrap">
                                <a href="{{ route('assignments.edit', $a) }}"
                                   class="btn btn-sm btn-outline-secondary" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form method="POST" action="{{ route('assignments.destroy', $a) }}"
                                      class="d-inline"
                                      onsubmit="return confirm('Delete this assignment? If the driver has run trips on it, deactivate it instead.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $ctx->isSuperAdmin() ? 7 : 6 }}" class="text-center text-muted py-4">
                                No assignments yet. <a href="{{ route('assignments.create') }}">Assign a driver to a vehicle &amp; route</a>.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
