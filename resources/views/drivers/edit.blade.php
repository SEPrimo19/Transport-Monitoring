@extends('layouts.app')

@section('title', 'Edit driver')
@section('heading', 'Edit driver')
@section('nav_drivers', 'active')

@section('content')
    @php $active = (string) old('is_active', $driver->is_active ? '1' : '0'); @endphp
    <div class="row">
        <div class="col-12 col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <form method="POST" action="{{ route('drivers.update', $driver) }}" novalidate>
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            @if ($isUsernameLogin)
                                <label class="form-label">Signs in with</label>
                                <input type="text" class="form-control" value="{{ $driver->employee_code }}" disabled readonly>
                                <div class="form-text">
                                    This is what the driver types into the app — no email address, no domain.
                                </div>
                            @else
                                <label class="form-label">Account email</label>
                                <input type="email" class="form-control" value="{{ $email ?? '—' }}" disabled readonly>
                                <div class="form-text">Managed by the driver's login — not editable here.</div>
                            @endif
                        </div>

                        <div class="mb-3">
                            <label for="full_name" class="form-label">Full name <span class="text-danger">*</span></label>
                            <input type="text" name="full_name" id="full_name"
                                   value="{{ old('full_name', $driver->profile?->full_name) }}"
                                   class="form-control @error('full_name') is-invalid @enderror"
                                   required minlength="2" maxlength="120" autofocus>
                            @error('full_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-3">
                            <label for="employee_code" class="form-label">Employee code <span class="text-danger">*</span></label>
                            <input type="text" name="employee_code" id="employee_code"
                                   value="{{ old('employee_code', $driver->employee_code) }}"
                                   class="form-control @error('employee_code') is-invalid @enderror"
                                   required minlength="2" maxlength="40"
                                   @if ($isUsernameLogin) readonly aria-describedby="employee_code_locked" @endif>
                            @error('employee_code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            @if ($isUsernameLogin)
                                <div class="form-text" id="employee_code_locked">
                                    This is the driver's sign-in username and can't be changed here — it is
                                    tied to their login.
                                </div>
                            @endif
                        </div>

                        <div class="mb-3">
                            <label for="license_number" class="form-label">License number <span class="text-danger">*</span></label>
                            <input type="text" name="license_number" id="license_number"
                                   value="{{ old('license_number', $driver->license_number) }}"
                                   class="form-control @error('license_number') is-invalid @enderror"
                                   required minlength="3" maxlength="64">
                            @error('license_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone</label>
                            <input type="text" name="phone" id="phone"
                                   value="{{ old('phone', $driver->phone) }}"
                                   class="form-control @error('phone') is-invalid @enderror"
                                   maxlength="30" placeholder="09xx xxx xxxx">
                            @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-3">
                            <label for="is_active" class="form-label">Status <span class="text-danger">*</span></label>
                            <select name="is_active" id="is_active"
                                    class="form-select @error('is_active') is-invalid @enderror" required>
                                <option value="1" @selected($active === '1')>Active</option>
                                <option value="0" @selected($active === '0')>Suspended</option>
                            </select>
                            @error('is_active') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <div class="form-text">
                                <strong>Suspended drivers cannot sign in.</strong> Their history is kept, and they
                                can be reactivated at any time. This blocks their whole account, including a
                                personal passenger login if this driver was promoted from one — suspending is a
                                full access revocation, not just a trip-start block.
                            </div>
                        </div>

                        <div class="d-flex gap-2 mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i>Update driver
                            </button>
                            <a href="{{ route('drivers.index') }}" class="btn btn-light">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card border-0 shadow-sm mt-4" id="reset-password">
                <div class="card-body p-4">
                    <h5 class="card-title mb-1"><i class="bi bi-key me-1"></i>Reset password</h5>
                    @if ($isUsernameLogin)
                        <p class="text-muted small mb-3">
                            Sets a new password for this driver's login immediately — no email is sent (this
                            account's address never receives mail). Share the new password with the driver directly.
                        </p>
                        <form method="POST" action="{{ route('drivers.reset-password', $driver) }}" novalidate>
                            @csrf
                            @method('PUT')

                            <div class="row g-3">
                                <div class="col-12 col-md-6">
                                    <label for="reset_password" class="form-label">New password <span class="text-danger">*</span></label>
                                    <input type="password" name="password" id="reset_password"
                                           class="form-control @error('password') is-invalid @enderror"
                                           required minlength="8" placeholder="At least 8 characters"
                                           autocomplete="new-password">
                                    @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="reset_password_confirmation" class="form-label">Confirm new password <span class="text-danger">*</span></label>
                                    <input type="password" name="password_confirmation" id="reset_password_confirmation"
                                           class="form-control" required minlength="8" autocomplete="new-password">
                                </div>
                            </div>

                            <div class="mt-3">
                                <button type="submit" class="btn btn-outline-danger">
                                    <i class="bi bi-key me-1"></i>Reset password
                                </button>
                            </div>
                        </form>
                    @else
                        <p class="text-muted small mb-0">
                            This driver signs in with their own email address, so a password reset isn't available
                            here — ask them to use "Forgot password" in the app instead.
                        </p>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
