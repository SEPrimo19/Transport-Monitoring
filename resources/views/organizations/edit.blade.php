@extends('layouts.app')

@section('title', 'Edit organization')
@section('heading', 'Edit organization')
@section('nav_organizations', 'active')

@section('content')
    <div class="row">
        <div class="col-12 col-lg-9">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <form method="POST" action="{{ route('organizations.update', $organization) }}" enctype="multipart/form-data" novalidate>
                        @csrf
                        @method('PUT')
                        @include('organizations._form')
                        <div class="d-flex gap-2 mt-3 form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i>Update organization
                            </button>
                            <a href="{{ route('organizations.index') }}" class="btn btn-light">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
