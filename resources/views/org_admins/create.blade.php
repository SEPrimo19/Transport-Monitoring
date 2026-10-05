@extends('layouts.app')

@section('title', 'Add org admin')
@section('heading', 'Add org admin')
@section('nav_org_admins', 'active')

@php
    // The promote form is the only one with an "email" field required as the lookup
    // key — if validation failed on it, that's the tab the admin was using. The
    // create-login form's own email field is optional, so it never drives this.
    $activeTab = ($errors->has('email') && old('username') === null) ? 'promote' : 'create';
@endphp

@section('content')
    <div class="row">
        <div class="col-12 col-lg-8">
            <ul class="nav nav-pills mb-3" id="addOrgAdminTabs" role="tablist">
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

            <div class="tab-content" id="addOrgAdminTabsContent">
                {{-- ── Create a new login ────────────────────────────────────────── --}}
                <div class="tab-pane fade {{ $activeTab === 'create' ? 'show active' : '' }}" id="create-login-pane"
                     role="tabpanel" aria-labelledby="create-login-tab">
                    <div class="alert alert-info d-flex">
                        <i class="bi bi-info-circle me-2 mt-1"></i>
                        <div>
                            <strong>For a cooperative's office administrator who has no reason to already have
                                a passenger account.</strong>
                            You choose a username and a password for them here — nothing for them to install or
                            confirm first. They sign in to this console with that username (or a real email, if
                            you give one below) and the password you set.
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm">
                        <div class="card-body p-4">
                            <form method="POST" action="{{ route('org-admins.login.store') }}" novalidate>
                                @csrf

                                <div class="mb-3">
                                    <label for="login_full_name" class="form-label">Full name <span class="text-danger">*</span></label>
                                    <input type="text" name="full_name" id="login_full_name" value="{{ old('full_name') }}"
                                           class="form-control @error('full_name') is-invalid @enderror"
                                           required minlength="2" maxlength="120" @if ($activeTab === 'create') autofocus @endif>
                                    @error('full_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="login_username" class="form-label">Username <span class="text-danger">*</span></label>
                                    <div class="input-group has-validation">
                                        <input type="text" name="username" id="login_username"
                                               value="{{ old('username') }}"
                                               class="form-control @error('username') is-invalid @enderror"
                                               required minlength="2" maxlength="40" pattern="[A-Za-z0-9._\-]+"
                                               placeholder="e.g. jsantos">
                                        @error('username') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="form-text">
                                        Letters, numbers, dots, hyphens and underscores only, and unique across
                                        all cooperatives. This is exactly what they type to sign in when no email
                                        is given below.
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="login_email" class="form-label">Email (optional)</label>
                                    <input type="email" name="email" id="login_email" value="{{ old('email') }}"
                                           class="form-control @error('email') is-invalid @enderror"
                                           maxlength="255" placeholder="leave blank to sign in with the username above">
                                    @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    <div class="form-text">
                                        <strong>Leave this blank unless they need it.</strong> With no email they
                                        sign in with the username above, and a super admin can reset their password
                                        from this list at any time.
                                        <br>
                                        Give a real email and they can sign in with it — but a super admin then
                                        <strong>cannot</strong> reset that login, because it belongs to them rather
                                        than to the cooperative. Recovery is by email only, so check that mail from
                                        Supabase actually reaches them before choosing this.
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="login_password" class="form-label">Password <span class="text-danger">*</span></label>
                                    <input type="password" name="password" id="login_password"
                                           class="form-control @error('password') is-invalid @enderror"
                                           required minlength="{{ \App\Support\AdminPasswordPolicy::MIN_LENGTH }}" placeholder="{{ \App\Support\AdminPasswordPolicy::hint() }}"
                                           autocomplete="new-password">
                                    @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    <div class="form-text">Share this with the admin directly. You can reset it from this list at
                                        any time — unless you give a real email above, which moves recovery to them.</div>
                                    @include('partials.password-requirements', ['field' => 'login_password'])
                                </div>

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
                                    <div class="form-text">They will administer this tenant only.</div>
                                </div>

                                <div class="d-flex gap-2 mt-4">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-person-plus me-1"></i>Create login
                                    </button>
                                    <a href="{{ route('org-admins.index') }}" class="btn btn-light">Cancel</a>
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
                            <strong>The person must already have an account with an email and password.</strong>
                            The console uses password login, so a Google-only account can't sign in until a
                            password is set. Promoting is single-role: their passenger access is replaced by
                            org-admin access (reversible by removing them here).
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm">
                        <div class="card-body p-4">
                            <form method="POST" action="{{ route('org-admins.store') }}" novalidate>
                                @csrf

                                <div class="mb-3">
                                    <label for="email" class="form-label">Account email <span class="text-danger">*</span></label>
                                    <input type="email" name="email" id="email" value="{{ old('email') }}"
                                           class="form-control @error('email') is-invalid @enderror"
                                           required @if ($activeTab === 'promote') autofocus @endif placeholder="person@example.com">
                                    @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    <div class="form-text">The exact email of an existing <strong>passenger</strong> account.</div>
                                </div>

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
                                    <div class="form-text">They will administer this tenant only.</div>
                                </div>

                                <div class="mb-3">
                                    <label for="full_name" class="form-label">Display name</label>
                                    <input type="text" name="full_name" id="full_name" value="{{ old('full_name') }}"
                                           class="form-control @error('full_name') is-invalid @enderror"
                                           minlength="2" maxlength="120" placeholder="optional — keeps their current name if blank">
                                    @error('full_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <div class="d-flex gap-2 mt-3 form-actions">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-person-check me-1"></i>Make org admin
                                    </button>
                                    <a href="{{ route('org-admins.index') }}" class="btn btn-light">Cancel</a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
