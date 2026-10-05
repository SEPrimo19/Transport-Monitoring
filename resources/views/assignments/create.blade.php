@extends('layouts.app')

@section('title', 'Add assignment')
@section('heading', 'Add assignment')
@section('nav_assignments', 'active')

@section('content')
    <div class="row">
        <div class="col-12 col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <form method="POST" action="{{ route('assignments.store') }}" novalidate>
                        @csrf
                        @include('assignments._form')
                        <div class="d-flex gap-2 mt-3 form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i>Save assignment
                            </button>
                            <a href="{{ route('assignments.index') }}" class="btn btn-light">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
            <p class="text-muted small mt-2 mb-0">
                <i class="bi bi-info-circle me-1"></i>
                A driver can only start a trip once they have an <strong>active</strong> assignment to a vehicle and route.
            </p>
        </div>
    </div>
@endsection
