@extends('layouts.app')

@section('title', 'Org Admins')
@section('heading', 'Org Admins')
@section('nav_org_admins', 'active')

@section('content')
    <div class="page-toolbar d-flex justify-content-between align-items-center">
        <p class="text-muted mb-0">
            {{ $admins->count() }} {{ Str::plural('org admin', $admins->count()) }} across all organizations
        </p>
        <a href="{{ route('org-admins.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>Add org admin
        </a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Organization</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($admins as $admin)
                        <tr>
                            <td class="fw-semibold">{{ $admin->full_name ?: '—' }}</td>
                            <td class="text-muted">{{ $emails[$admin->id] ?? '—' }}</td>
                            <td>{{ $orgNames[$admin->organization_id] ?? '— (no org)' }}</td>
                            <td class="text-end text-nowrap">
                                <form method="POST" action="{{ route('org-admins.destroy', $admin) }}"
                                      class="d-inline"
                                      data-confirm="Remove {{ $admin->full_name ?: 'this account' }} as an org admin? They become a plain passenger and lose console access." data-confirm-action="Remove">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="bi bi-person-dash me-1"></i>Remove admin
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="p-0">
                                <div class="empty-state text-center">
                                    <i class="bi bi-person-gear"></i>
                                    <p class="empty-state-title mb-1">No org admins yet</p>
                                    <p class="text-muted small mb-0">
                                        <a href="{{ route('org-admins.create') }}">Promote a passenger to administer a tenant</a>.
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
        Super-admins are created manually in the database (bootstrap/safety) and aren't listed or managed here.
    </p>
@endsection
