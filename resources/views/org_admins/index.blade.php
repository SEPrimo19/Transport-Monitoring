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
                        <th>Signs in with</th>
                        <th>Organization</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($admins as $admin)
                        @php
                            $rowEmail = $emails[$admin->id] ?? null;
                            $isUsernameLogin = \App\Support\OrgAdminAccountEmail::isUsernameLogin($rowEmail);
                        @endphp
                        <tr>
                            <td class="fw-semibold">{{ $admin->full_name ?: '—' }}</td>
                            {{-- An admin-created login's synthetic address is an implementation
                                 detail the operator can do nothing with — show what they actually
                                 tell the admin to type instead. A real (supplied or promoted)
                                 email still shows as-is. --}}
                            <td>
                                @if ($isUsernameLogin)
                                    <span class="fw-semibold">{{ \Illuminate\Support\Str::before($rowEmail, '@') }}</span>
                                    <span class="badge text-bg-light border ms-1">username</span>
                                @else
                                    {{ $rowEmail ?? '—' }}
                                @endif
                            </td>
                            <td>{{ $orgNames[$admin->organization_id] ?? '— (no org)' }}</td>
                            <td class="text-end text-nowrap">
                                @if ($isUsernameLogin)
                                    <button type="button" class="btn btn-sm btn-outline-secondary" title="Reset password"
                                            data-bs-toggle="modal" data-bs-target="#resetPasswordModal-{{ $admin->id }}">
                                        <i class="bi bi-key"></i>
                                    </button>
                                @endif
                                <form method="POST" action="{{ route('org-admins.destroy', $admin) }}"
                                      class="d-inline"
                                      data-confirm="Remove {{ $admin->full_name ?: 'this account' }} as an org admin?@if ($isUsernameLogin) Their sign-in is disabled and this username stays taken.@else They keep their own passenger account; only console access is removed.@endif" data-confirm-action="Remove">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="bi bi-person-dash me-1"></i>Remove admin
                                    </button>
                                </form>
                            </td>
                        </tr>

                        @if ($isUsernameLogin)
                            {{-- One modal per admin-created login. Only rendered for those — a
                                 promoted/real-email account's password is theirs to recover, not a
                                 platform admin's to set (enforced again server-side by the Edge
                                 Function's own domain check). --}}
                            <div class="modal fade" id="resetPasswordModal-{{ $admin->id }}" tabindex="-1"
                                 aria-labelledby="resetPasswordLabel-{{ $admin->id }}" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <form method="POST" action="{{ route('org-admins.reset-password', $admin) }}" novalidate>
                                            @csrf
                                            @method('PUT')
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="resetPasswordLabel-{{ $admin->id }}">
                                                    Reset password — {{ $admin->full_name ?: 'this admin' }}
                                                </h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <p class="text-muted small">
                                                    Sets a new password for this login immediately — no email is sent
                                                    (this account's address never receives mail). Share the new
                                                    password with the admin directly.
                                                </p>
                                                <div class="mb-3">
                                                    <label for="reset_password_{{ $admin->id }}" class="form-label">New password</label>
                                                    <input type="password" name="password" id="reset_password_{{ $admin->id }}"
                                                           class="form-control" required minlength="8"
                                                           placeholder="At least 8 characters" autocomplete="new-password">
                                                </div>
                                                <div class="mb-0">
                                                    <label for="reset_password_confirmation_{{ $admin->id }}" class="form-label">Confirm new password</label>
                                                    <input type="password" name="password_confirmation" id="reset_password_confirmation_{{ $admin->id }}"
                                                           class="form-control" required minlength="8" autocomplete="new-password">
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-outline-danger">
                                                    <i class="bi bi-key me-1"></i>Reset password
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @endif
                    @empty
                        <tr>
                            <td colspan="4" class="p-0">
                                <div class="empty-state text-center">
                                    <i class="bi bi-person-gear"></i>
                                    <p class="empty-state-title mb-1">No org admins yet</p>
                                    <p class="text-muted small mb-0">
                                        <a href="{{ route('org-admins.create') }}">Add one, or promote a passenger to administer a tenant</a>.
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
