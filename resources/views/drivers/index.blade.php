@extends('layouts.app')

@section('title', 'Drivers')
@section('heading', 'Drivers')
@section('nav_drivers', 'active')

@section('content')
    <div class="page-toolbar d-flex justify-content-between align-items-center">
        <p class="text-muted mb-0">
            {{ $drivers->count() }} {{ Str::plural('driver', $drivers->count()) }}
            {{ $ctx->isSuperAdmin() ? 'across all organizations' : 'in your organization' }}
        </p>
        <a href="{{ route('drivers.create') }}" class="btn btn-primary">
            <i class="bi bi-person-plus me-1"></i>Add driver
        </a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Employee code</th>
                        <th>License no.</th>
                        <th>Phone</th>
                        <th>Status</th>
                        @if ($ctx->isSuperAdmin())
                            <th>Organization</th>
                        @endif
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($drivers as $driver)
                        <tr>
                            <td class="fw-semibold">{{ $driver->profile?->full_name ?? '—' }}</td>
                            <td>{{ $emails[$driver->profile_id] ?? '—' }}</td>
                            <td>{{ $driver->employee_code }}</td>
                            <td>{{ $driver->license_number }}</td>
                            <td>{{ $driver->phone ?? '—' }}</td>
                            <td><span class="badge {{ $driver->statusBadge() }}">{{ $driver->statusLabel() }}</span></td>
                            @if ($ctx->isSuperAdmin())
                                <td>{{ $orgNames[$driver->organization_id] ?? '—' }}</td>
                            @endif
                            <td class="text-end text-nowrap">
                                <a href="{{ route('drivers.edit', $driver) }}"
                                   class="btn btn-sm btn-outline-secondary" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form method="POST" action="{{ route('drivers.destroy', $driver) }}"
                                      class="d-inline"
                                      onsubmit="return confirm('Remove {{ $driver->profile?->full_name }} from the fleet? They will revert to a passenger account.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Remove from fleet">
                                        <i class="bi bi-person-dash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $ctx->isSuperAdmin() ? 8 : 7 }}" class="p-0">
                                <div class="empty-state text-center">
                                    <i class="bi bi-person-badge"></i>
                                    <p class="empty-state-title mb-1">No drivers yet</p>
                                    <p class="text-muted small mb-0">
                                        <a href="{{ route('drivers.create') }}">Add the first one</a>.
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
