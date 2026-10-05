@extends('layouts.app')

@section('title', 'Edit assignment')
@section('heading', 'Edit assignment')
@section('nav_assignments', 'active')

@section('content')
    <div class="row">
        <div class="col-12 col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <form method="POST" action="{{ route('assignments.update', $assignment) }}" novalidate>
                        @csrf
                        @method('PUT')
                        @include('assignments._form')
                        <div class="d-flex gap-2 mt-3 form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i>Update assignment
                            </button>
                            <a href="{{ route('assignments.index') }}" class="btn btn-light">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
