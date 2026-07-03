@extends('layouts.app')

@section('title', 'Add driver')
@section('heading', 'Add driver')
@section('nav_drivers', 'active')

@section('content')
    <div class="row">
        <div class="col-12 col-lg-7">
            <div class="alert alert-info d-flex">
                <i class="bi bi-info-circle me-2 mt-1"></i>
                <div>
                    <strong>The driver must register in the app first.</strong>
                    They install AMOEC Track and sign up (they start as a passenger). Then enter their
                    exact email below to promote them to a driver in your organization.
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <form method="POST" action="{{ route('drivers.store') }}" novalidate>
                        @csrf

                        <div class="mb-3">
                            <label for="email" class="form-label">Account email <span class="text-danger">*</span></label>
                            <input type="email" name="email" id="email" value="{{ old('email') }}"
                                   class="form-control @error('email') is-invalid @enderror"
                                   required autofocus placeholder="driver@example.com">
                            @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <div class="form-text">Must exactly match the email they registered with in the app.</div>
                        </div>

                        <div class="mb-3">
                            <label for="full_name" class="form-label">Full name <span class="text-danger">*</span></label>
                            <input type="text" name="full_name" id="full_name" value="{{ old('full_name') }}"
                                   class="form-control @error('full_name') is-invalid @enderror"
                                   required minlength="2" maxlength="120">
                            @error('full_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <div class="form-text">Confirm/correct the driver's name for your records.</div>
                        </div>

                        <div class="mb-3">
                            <label for="employee_code" class="form-label">Employee code <span class="text-danger">*</span></label>
                            <input type="text" name="employee_code" id="employee_code" value="{{ old('employee_code') }}"
                                   class="form-control @error('employee_code') is-invalid @enderror"
                                   required minlength="2" maxlength="40">
                            @error('employee_code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-3">
                            <label for="license_number" class="form-label">License number <span class="text-danger">*</span></label>
                            <input type="text" name="license_number" id="license_number" value="{{ old('license_number') }}"
                                   class="form-control @error('license_number') is-invalid @enderror"
                                   required minlength="3" maxlength="64">
                            @error('license_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone</label>
                            <input type="text" name="phone" id="phone" value="{{ old('phone') }}"
                                   class="form-control @error('phone') is-invalid @enderror"
                                   maxlength="30" placeholder="09xx xxx xxxx">
                            @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        @if ($ctx->isSuperAdmin())
                            <div class="mb-3">
                                <label for="organization_id" class="form-label">Organization <span class="text-danger">*</span></label>
                                <select name="organization_id" id="organization_id"
                                        class="form-select @error('organization_id') is-invalid @enderror" required>
                                    <option value="">— select an organization —</option>
                                    @foreach ($organizations as $org)
                                        <option value="{{ $org->id }}" @selected(old('organization_id') === $org->id)>{{ $org->name }}</option>
                                    @endforeach
                                </select>
                                @error('organization_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                <div class="form-text">The driver will be promoted into this tenant.</div>
                            </div>
                        @endif

                        <div class="d-flex gap-2 mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-person-check me-1"></i>Promote to driver
                            </button>
                            <a href="{{ route('drivers.index') }}" class="btn btn-light">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
