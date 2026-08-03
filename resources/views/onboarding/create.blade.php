@extends('layouts.app')

@section('title', 'Onboard a cooperative')
@section('heading', 'Onboard a cooperative')
@section('nav_onboarding', 'active')

@section('content')
    <div class="page-toolbar d-flex justify-content-between align-items-center">
        <p class="text-muted mb-0">
            Create a new tenant and hand it to its first administrator in one step.
        </p>
        <a href="{{ route('organizations.index') }}" class="btn btn-sm btn-light">
            <i class="bi bi-arrow-left me-1"></i>Organizations
        </a>
    </div>

    <form method="POST" action="{{ route('onboarding.store') }}" class="col-12 col-xl-10">
        @csrf

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <h2 class="h6 mb-3"><i class="bi bi-buildings me-1"></i>Organization</h2>
                @include('organizations._form', ['organization' => $organization])
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <h2 class="h6 mb-1"><i class="bi bi-person-gear me-1"></i>First administrator</h2>
                <p class="text-muted small">
                    Promote an existing <strong>passenger</strong> account to run this cooperative. They must have
                    signed up in the app already — this doesn't create a new login.
                </p>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="admin_email" class="form-label">Passenger email <span class="text-danger">*</span></label>
                        <input type="email" name="admin_email" id="admin_email" value="{{ old('admin_email') }}"
                               class="form-control @error('admin_email') is-invalid @enderror"
                               required maxlength="200" placeholder="person@example.com">
                        @error('admin_email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="admin_full_name" class="form-label">Administrator name</label>
                        <input type="text" name="admin_full_name" id="admin_full_name" value="{{ old('admin_full_name') }}"
                               class="form-control @error('admin_full_name') is-invalid @enderror"
                               maxlength="120" placeholder="optional — keeps their current name if blank">
                        @error('admin_full_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-rocket-takeoff me-1"></i>Create &amp; assign admin
            </button>
            <a href="{{ route('organizations.index') }}" class="btn btn-light">Cancel</a>
        </div>
    </form>
@endsection
