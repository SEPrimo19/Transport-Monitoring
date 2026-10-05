@extends('layouts.app')

@section('title', 'Add driver')
@section('heading', 'Add driver')
@section('nav_drivers', 'active')

@php
    // The promote form is the only one with an "email" field — if validation
    // failed on it (or it was filled in), that's the tab the admin was using.
    $activeTab = (old('email') !== null || $errors->has('email')) ? 'promote' : 'create';
@endphp

@section('content')
    <div class="row">
        <div class="col-12 col-lg-8">
            <ul class="nav nav-pills mb-3" id="addDriverTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link {{ $activeTab === 'create' ? 'active' : '' }}" id="create-login-tab"
                            data-bs-toggle="pill" data-bs-target="#create-login-pane" type="button" role="tab"
                            aria-controls="create-login-pane" aria-selected="{{ $activeTab === 'create' ? 'true' : 'false' }}">
                        <i class="bi bi-person-plus me-1"></i>Create a new login
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link {{ $activeTab === 'promote' ? 'active' : '' }}" id="promote-tab"
                            data-bs-toggle="pill" data-bs-target="#promote-pane" type="button" role="tab"
                            aria-controls="promote-pane" aria-selected="{{ $activeTab === 'promote' ? 'true' : 'false' }}">
                        <i class="bi bi-person-check me-1"></i>Promote an existing app account
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="addDriverTabsContent">
                {{-- ── Create a new login ────────────────────────────────────────── --}}
                <div class="tab-pane fade {{ $activeTab === 'create' ? 'show active' : '' }}" id="create-login-pane"
                     role="tabpanel" aria-labelledby="create-login-tab">
                    <div class="alert alert-info d-flex">
                        <i class="bi bi-info-circle me-2 mt-1"></i>
                        <div>
                            <strong>For drivers without a Gmail account, or who aren't comfortable signing up
                                themselves.</strong>
                            You choose a username and a password for them here — nothing for the driver to install
                            or confirm first. They sign in on the app with that username and the password you set.
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm">
                        <div class="card-body p-4">
                            <form method="POST" action="{{ route('drivers.login.store') }}" novalidate>
                                @csrf

                                <div class="mb-3">
                                    <label for="login_full_name" class="form-label">Full name <span class="text-danger">*</span></label>
                                    <input type="text" name="full_name" id="login_full_name" value="{{ old('full_name') }}"
                                           class="form-control @error('full_name') is-invalid @enderror"
                                           required minlength="2" maxlength="120" @if ($activeTab === 'create') autofocus @endif>
                                    @error('full_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="login_employee_code" class="form-label">Username <span class="text-danger">*</span></label>
                                    <div class="input-group has-validation">
                                        <input type="text" name="employee_code" id="login_employee_code"
                                               value="{{ old('employee_code') }}"
                                               class="form-control @error('employee_code') is-invalid @enderror"
                                               required minlength="2" maxlength="40" pattern="[A-Za-z0-9._\-]+"
                                               placeholder="e.g. jsantos">
                                        @error('employee_code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="form-text">
                                        Letters, numbers, dots, hyphens and underscores only. This doubles as the
                                        employee code, and is what the driver types to sign in — the address shown
                                        is never emailed to anyone.
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="login_license_number" class="form-label">License number <span class="text-danger">*</span></label>
                                    <input type="text" name="license_number" id="login_license_number"
                                           value="{{ old('license_number') }}"
                                           class="form-control @error('license_number') is-invalid @enderror"
                                           required minlength="3" maxlength="64">
                                    @error('license_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="login_password" class="form-label">Password <span class="text-danger">*</span></label>
                                    <input type="password" name="password" id="login_password"
                                           class="form-control @error('password') is-invalid @enderror"
                                           required minlength="{{ \App\Support\AdminPasswordPolicy::MIN_LENGTH }}" placeholder="{{ \App\Support\AdminPasswordPolicy::hint() }}"
                                           autocomplete="new-password">
                                    @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    @include('partials.password-requirements', ['field' => 'login_password'])
                                    <div class="form-text">Share this with the driver directly. You can reset it any time from their profile.</div>
                                </div>

                                @if ($ctx->isSuperAdmin())
                                    <div class="mb-3">
                                        <label for="login_organization_id" class="form-label">Organization <span class="text-danger">*</span></label>
                                        <select name="organization_id" id="login_organization_id"
                                                class="form-select @error('organization_id') is-invalid @enderror" required>
                                            <option value="">— select an organization —</option>
                                            @foreach ($organizations as $org)
                                                <option value="{{ $org->id }}" @selected(old('organization_id') === $org->id)>{{ $org->name }}</option>
                                            @endforeach
                                        </select>
                                        @error('organization_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                        <div class="form-text">The driver's login will belong to this tenant.</div>
                                    </div>
                                @endif

                                <div class="d-flex gap-2 mt-4">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-person-plus me-1"></i>Create login
                                    </button>
                                    <a href="{{ route('drivers.index') }}" class="btn btn-light">Cancel</a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                {{-- ── Promote an existing app account ───────────────────────────── --}}
                <div class="tab-pane fade {{ $activeTab === 'promote' ? 'show active' : '' }}" id="promote-pane"
                     role="tabpanel" aria-labelledby="promote-tab">
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
                                           required @if ($activeTab === 'promote') autofocus @endif placeholder="driver@example.com">
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
        </div>
    </div>
@endsection
