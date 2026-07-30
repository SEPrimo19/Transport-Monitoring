@extends('layouts.app')

@section('title', 'Organizations')
@section('heading', 'Organizations')
@section('nav_organizations', 'active')

@section('content')
    <div class="page-toolbar d-flex justify-content-between align-items-center">
        <p class="text-muted mb-0">
            {{ $organizations->count() }} {{ Str::plural('tenant', $organizations->count()) }} on the platform
        </p>
        <a href="{{ route('organizations.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>Add organization
        </a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Brand</th>
                        <th>Name</th>
                        <th>Slug</th>
                        <th class="text-end">Vehicles</th>
                        <th class="text-end">Drivers</th>
                        <th>Contact</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($organizations as $org)
                        <tr>
                            <td>
                                <span class="d-inline-block rounded border"
                                      style="width:1.5rem;height:1.5rem;background: {{ $org->primary_color ?: '#e9ecef' }};"
                                      title="{{ $org->primary_color ?: 'no brand colour' }}"></span>
                            </td>
                            <td class="fw-semibold">{{ $org->name }}</td>
                            <td><code>{{ $org->slug }}</code></td>
                            <td class="text-end">{{ (int) ($vehicleCounts[$org->id] ?? 0) }}</td>
                            <td class="text-end">{{ (int) ($driverCounts[$org->id] ?? 0) }}</td>
                            <td class="text-muted small">{{ $org->contact_email ?: '—' }}</td>
                            <td><span class="badge {{ $org->statusBadge() }}">{{ $org->statusLabel() }}</span></td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('organizations.edit', $org) }}"
                                   class="btn btn-sm btn-outline-secondary" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="p-0">
                                <div class="empty-state text-center">
                                    <i class="bi bi-buildings"></i>
                                    <p class="empty-state-title mb-1">No organizations yet</p>
                                    <p class="text-muted small mb-0">
                                        <a href="{{ route('organizations.create') }}">Add the first one</a>.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <p class="text-muted small mt-2 mb-0">
        <i class="bi bi-shield-lock me-1"></i>
        Tenants aren't deleted — set a tenant to <strong>Suspended</strong> to disable it while keeping its data.
    </p>
@endsection
