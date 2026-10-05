@extends('layouts.app')

@section('title', 'Add organization')
@section('heading', 'Add organization')
@section('nav_organizations', 'active')

@section('content')
    <div class="row">
        <div class="col-12 col-lg-9">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <form method="POST" action="{{ route('organizations.store') }}" novalidate>
                        @csrf
                        @include('organizations._form')
                        <div class="d-flex gap-2 mt-3 form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i>Create organization
                            </button>
                            <a href="{{ route('organizations.index') }}" class="btn btn-light">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
            <p class="text-muted small mt-2 mb-0">
                <i class="bi bi-info-circle me-1"></i>
                A new tenant starts empty. Add its terminals, routes, vehicles and admins afterwards.
            </p>
        </div>
    </div>
@endsection
