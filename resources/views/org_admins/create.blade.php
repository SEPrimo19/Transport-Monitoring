@extends('layouts.app')

@section('title', 'Add org admin')
@section('heading', 'Add org admin')
@section('nav_org_admins', 'active')

@section('content')
    <div class="row">
        <div class="col-12 col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <form method="POST" action="{{ route('org-admins.store') }}" novalidate>
                        @csrf

                        <div class="mb-3">
                            <label for="email" class="form-label">Account email <span class="text-danger">*</span></label>
                            <input type="email" name="email" id="email" value="{{ old('email') }}"
                                   class="form-control @error('email') is-invalid @enderror"
                                   required autofocus placeholder="person@example.com">
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

                        <div class="d-flex gap-2 mt-3">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-person-check me-1"></i>Make org admin
                            </button>
                            <a href="{{ route('org-admins.index') }}" class="btn btn-light">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="alert alert-light border small mt-3 mb-0">
                <i class="bi bi-info-circle me-1"></i>
                The person must already have an account with an <strong>email and password</strong> — the console uses
                password login, so a Google-only account can't sign in until a password is set. Promoting is
                single-role: their passenger access is replaced by org-admin access (reversible by removing them here).
            </div>
        </div>
    </div>
@endsection
