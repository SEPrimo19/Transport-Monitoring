@extends('layouts.app')

@section('title', 'Schedules')
@section('heading', 'Schedules')
@section('nav_schedules', 'active')

@section('content')
    <div class="page-toolbar d-flex justify-content-between align-items-center">
        <p class="text-muted mb-0">
            {{ $schedules->count() }} {{ Str::plural('timetable', $schedules->count()) }}
            {{ $ctx->isSuperAdmin() ? 'across all organizations' : 'in your organization' }}
        </p>
        <a href="{{ route('schedules.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>Add schedule
        </a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Route</th>
                        <th>Timetable</th>
                        <th>Days</th>
                        <th class="text-end">Departures</th>
                        <th>Status</th>
                        @if ($ctx->isSuperAdmin())
                            <th>Organization</th>
                        @endif
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($schedules as $s)
                        <tr>
                            <td class="fw-semibold">
                                {{ $s->route?->code ?? '—' }}
                                @if ($s->label)<div class="text-muted small">{{ $s->label }}</div>@endif
                            </td>
                            <td class="small">{{ $s->summary() }}</td>
                            <td class="small text-muted">{{ $s->daysLabel() }}</td>
                            <td class="text-end"><span class="badge text-bg-info">{{ $s->departureCount() }}/day</span></td>
                            <td><span class="badge {{ $s->statusBadge() }}">{{ $s->is_active ? 'Active' : 'Inactive' }}</span></td>
                            @if ($ctx->isSuperAdmin())
                                <td>{{ $orgNames[$s->organization_id] ?? '—' }}</td>
                            @endif
                            <td class="text-end text-nowrap">
                                <a href="{{ route('schedules.edit', $s) }}" class="btn btn-sm btn-outline-secondary" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form method="POST" action="{{ route('schedules.destroy', $s) }}" class="d-inline"
                                      onsubmit="return confirm('Delete this schedule? This cannot be undone.');">
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
                            <td colspan="{{ $ctx->isSuperAdmin() ? 7 : 6 }}" class="p-0">
                                <div class="empty-state text-center">
                                    <i class="bi bi-calendar-week"></i>
                                    <p class="empty-state-title mb-1">No schedules yet</p>
                                    <p class="text-muted small mb-0">
                                        <a href="{{ route('schedules.create') }}">Publish a route timetable</a>.
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
