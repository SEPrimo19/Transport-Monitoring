@extends('layouts.app')

@section('title', 'Vehicles')
@section('heading', 'Vehicles')
@section('nav_vehicles', 'active')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <p class="text-muted mb-0">
            {{ $vehicles->count() }} {{ Str::plural('vehicle', $vehicles->count()) }}
            {{ $ctx->isSuperAdmin() ? 'across all organizations' : 'in your fleet' }}
        </p>
        <a href="{{ route('vehicles.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>Add vehicle
        </a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Plate</th>
                        <th>Fleet code</th>
                        <th class="text-end">Capacity</th>
                        <th>Status</th>
                        @if ($ctx->isSuperAdmin())
                            <th>Organization</th>
                        @endif
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($vehicles as $vehicle)
                        <tr>
                            <td class="fw-semibold">{{ $vehicle->plate_number }}</td>
                            <td>{{ $vehicle->fleet_code ?? '—' }}</td>
                            <td class="text-end">{{ $vehicle->capacity_total }}</td>
                            <td>
                                <span class="badge {{ $vehicle->statusBadge() }} text-capitalize">{{ $vehicle->status }}</span>
                                @unless ($vehicle->is_active)
                                    <span class="badge text-bg-light border" title="Not visible to passengers">hidden</span>
                                @endunless
                            </td>
                            @if ($ctx->isSuperAdmin())
                                <td>{{ $vehicle->organization?->name ?? '—' }}</td>
                            @endif
                            <td class="text-end text-nowrap">
                                <a href="{{ route('vehicles.edit', $vehicle) }}"
                                   class="btn btn-sm btn-outline-secondary" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form method="POST" action="{{ route('vehicles.destroy', $vehicle) }}"
                                      class="d-inline"
                                      onsubmit="return confirm('Delete {{ $vehicle->plate_number }}? This cannot be undone.');">
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
                            <td colspan="{{ $ctx->isSuperAdmin() ? 6 : 5 }}" class="text-center text-muted py-4">
                                No vehicles yet. <a href="{{ route('vehicles.create') }}">Add the first one</a>.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
