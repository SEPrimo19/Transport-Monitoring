@extends('layouts.app')

@section('title', 'My account')
@section('heading', 'My account')

@section('content')
    <div class="row">
        <div class="col-12 col-lg-7">
            {{-- ── Profile ──────────────────────────────────────────────── --}}
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <h2 class="h6 text-muted mb-3"><i class="bi bi-person me-1"></i>Profile</h2>

                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" value="{{ $ctx->email }}" class="form-control" disabled readonly>
                        <div class="form-text">Sign-in email is managed with your account and isn't changed here.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <div><span class="badge text-bg-secondary">{{ $ctx->role }}</span></div>
                    </div>
                </div>
            </div>

            {{-- ── Update name + password ───────────────────────────────── --}}
            <form method="POST" action="{{ route('account.update') }}">
                @csrf
                @method('PUT')

                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body">
                        <h2 class="h6 text-muted mb-3"><i class="bi bi-pencil me-1"></i>Display name</h2>
                        <div class="mb-1">
                            <label for="full_name" class="form-label">Full name <span class="text-danger">*</span></label>
                            <input type="text" name="full_name" id="full_name"
                                   value="{{ old('full_name', $ctx->fullName) }}"
                                   class="form-control @error('full_name') is-invalid @enderror"
                                   required minlength="2" maxlength="120">
                            @error('full_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body">
                        <h2 class="h6 text-muted mb-3"><i class="bi bi-key me-1"></i>Change password</h2>
                        <p class="text-muted small">Leave these blank to keep your current password.</p>

                        <div class="mb-3">
                            <label for="current_password" class="form-label">Current password</label>
                            <input type="password" name="current_password" id="current_password"
                                   class="form-control @error('current_password') is-invalid @enderror"
                                   autocomplete="current-password">
                            @error('current_password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="new_password" class="form-label">New password</label>
                                <input type="password" name="new_password" id="new_password"
                                       class="form-control @error('new_password') is-invalid @enderror"
                                       autocomplete="new-password" minlength="8">
                                @error('new_password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                <div class="form-text">{{ \App\Support\AdminPasswordPolicy::hint() }}</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="new_password_confirmation" class="form-label">Confirm new password</label>
                                <input type="password" name="new_password_confirmation" id="new_password_confirmation"
                                       class="form-control" autocomplete="new-password" minlength="8">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i>Save changes
                    </button>
                    <a href="{{ route('dashboard') }}" class="btn btn-light">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
