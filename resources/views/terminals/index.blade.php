@extends('layouts.app')

@section('title', 'Terminals')
@section('heading', 'Terminals')
@section('nav_terminals', 'active')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <p class="text-muted mb-0">
            {{ $terminals->count() }} {{ Str::plural('terminal', $terminals->count()) }}
            {{ $ctx->isSuperAdmin() ? 'across all organizations' : 'in your organization' }}
        </p>
        <a href="{{ route('terminals.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>Add terminal
        </a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Address</th>
                        <th>Coordinates</th>
                        <th class="text-end">Routes</th>
                        <th>Status</th>
                        @if ($ctx->isSuperAdmin())
                            <th>Organization</th>
                        @endif
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($terminals as $terminal)
                        @php $uses = (int) ($routeUse[$terminal->id] ?? 0); @endphp
                        <tr>
                            <td class="fw-semibold">{{ $terminal->code }}</td>
                            <td>{{ $terminal->name }}</td>
                            <td class="text-muted">{{ $terminal->address ?: '—' }}</td>
                            <td>
                                @if ($terminal->hasCoordinates())
                                    <span class="text-muted small">
                                        <i class="bi bi-geo-alt me-1"></i>{{ rtrim(rtrim(number_format($terminal->latitude, 5), '0'), '.') }}, {{ rtrim(rtrim(number_format($terminal->longitude, 5), '0'), '.') }}
                                    </span>
                                @else
                                    <span class="badge text-bg-light border" title="No coordinates set">none</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @if ($uses > 0)
                                    <span class="badge text-bg-info" title="Routes using this terminal">{{ $uses }}</span>
                                @else
                                    <span class="text-muted">0</span>
                                @endif
                            </td>
                            <td><span class="badge {{ $terminal->statusBadge() }}">{{ $terminal->is_active ? 'Active' : 'Inactive' }}</span></td>
                            @if ($ctx->isSuperAdmin())
                                <td>{{ $orgNames[$terminal->organization_id] ?? '—' }}</td>
                            @endif
                            <td class="text-end text-nowrap">
                                <a href="{{ route('terminals.edit', $terminal) }}"
                                   class="btn btn-sm btn-outline-secondary" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                @if ($uses > 0)
                                    <button type="button" class="btn btn-sm btn-outline-secondary" disabled
                                            title="Used by {{ $uses }} {{ Str::plural('route', $uses) }} — reassign those first">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                @else
                                    <form method="POST" action="{{ route('terminals.destroy', $terminal) }}"
                                          class="d-inline"
                                          onsubmit="return confirm('Delete terminal {{ $terminal->code }}? This cannot be undone.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $ctx->isSuperAdmin() ? 8 : 7 }}" class="text-center text-muted py-4">
                                No terminals yet. <a href="{{ route('terminals.create') }}">Add the first one</a>.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
