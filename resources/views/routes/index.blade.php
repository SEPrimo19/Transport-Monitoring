@extends('layouts.app')

@section('title', 'Routes')
@section('heading', 'Routes')
@section('nav_routes', 'active')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <p class="text-muted mb-0">
            {{ $routes->count() }} {{ Str::plural('route', $routes->count()) }}
            {{ $ctx->isSuperAdmin() ? 'across all organizations' : 'in your organization' }}
        </p>
        <a href="{{ route('routes.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>Add route
        </a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Origin → Destination</th>
                        <th class="text-end">Distance</th>
                        <th class="text-end">Duration</th>
                        <th>Polyline</th>
                        <th>Status</th>
                        @if ($ctx->isSuperAdmin())
                            <th>Organization</th>
                        @endif
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($routes as $route)
                        <tr>
                            <td class="fw-semibold">{{ $route->code }}</td>
                            <td>{{ $route->name }}</td>
                            <td>
                                <span class="text-muted">{{ $route->originTerminal?->code ?? '—' }}</span>
                                <i class="bi bi-arrow-right mx-1"></i>
                                <span class="text-muted">{{ $route->destinationTerminal?->code ?? '—' }}</span>
                            </td>
                            <td class="text-end">{{ rtrim(rtrim($route->distance_km, '0'), '.') }} km</td>
                            <td class="text-end">{{ $route->duration_minutes ? $route->duration_minutes . ' min' : '—' }}</td>
                            <td>
                                @if ($route->geometry)
                                    <span class="badge text-bg-info" title="Polyline point count">
                                        <i class="bi bi-bezier2 me-1"></i>{{ number_format($route->geometry->point_count) }} pts
                                    </span>
                                @else
                                    <span class="badge text-bg-light border" title="No polyline stored">none</span>
                                @endif
                            </td>
                            <td><span class="badge {{ $route->statusBadge() }}">{{ $route->is_active ? 'Active' : 'Inactive' }}</span></td>
                            @if ($ctx->isSuperAdmin())
                                <td>{{ $orgNames[$route->organization_id] ?? '—' }}</td>
                            @endif
                            <td class="text-end text-nowrap">
                                <a href="{{ route('routes.edit', $route) }}"
                                   class="btn btn-sm btn-outline-secondary" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form method="POST" action="{{ route('routes.destroy', $route) }}"
                                      class="d-inline"
                                      onsubmit="return confirm('Delete route {{ $route->code }}? This cannot be undone.');">
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
                            <td colspan="{{ $ctx->isSuperAdmin() ? 9 : 8 }}" class="text-center text-muted py-4">
                                No routes yet. <a href="{{ route('routes.create') }}">Add the first one</a>.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
