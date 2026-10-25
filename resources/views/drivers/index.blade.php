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
        @include('partials.table-search', ['placeholder' => 'Search name, username, licence, phone…'])
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Signs in with</th>
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
                            <td class="fw-semibold">
                                <a href="{{ route('drivers.show', $driver) }}" class="text-decoration-none">{{ $driver->profile?->full_name ?? '—' }}</a>
                            </td>
                            {{-- An admin-created login's address is a synthetic one we derive from the
                                 username; showing it here is an implementation detail the operator can do
                                 nothing with. Show what they actually tell the driver to type. A promoted
                                 driver's address IS their real email, so that still shows. --}}
                            @php $rowEmail = $emails[$driver->profile_id] ?? null; @endphp
                            <td>
                                @if (\App\Support\DriverAccountEmail::isUsernameLogin($rowEmail))
                                    <span class="fw-semibold">{{ $driver->employee_code }}</span>
                                    <span class="badge text-bg-light border ms-1">username</span>
                                @else
                                    {{ $rowEmail ?? '—' }}
                                @endif
                            </td>
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
                                <a href="{{ route('drivers.edit', $driver) }}#reset-password"
                                   class="btn btn-sm btn-outline-secondary" title="Reset password">
                                    <i class="bi bi-key"></i>
                                </a>
                                @php
                                    $removeConfirm = \App\Support\DriverAccountEmail::isUsernameLogin($emails[$driver->profile_id] ?? null)
                                        ? "Remove {$driver->profile?->full_name} from the fleet? Their sign-in will be deleted and the username released — this can't be undone."
                                        : "Remove {$driver->profile?->full_name} from the fleet? They will revert to a passenger account.";
                                @endphp
                                <form method="POST" action="{{ route('drivers.destroy', $driver) }}"
                                      class="d-inline"
                                      data-confirm="{{ $removeConfirm }}" data-confirm-action="Remove">
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
