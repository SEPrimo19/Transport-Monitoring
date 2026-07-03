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
                            <label class="form-label">Account email</label>
                            <input type="email" class="form-control" value="{{ $email ?? '—' }}" disabled readonly>
                            <div class="form-text">Managed by the driver's login — not editable here.</div>
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
                                   required minlength="2" maxlength="40">
                            @error('employee_code') <div class="invalid-feedback">{{ $message }}</div> @enderror
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
                                <strong>Suspended</strong> drivers keep their account and history but can't run trips.
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
        </div>
    </div>
@endsection
