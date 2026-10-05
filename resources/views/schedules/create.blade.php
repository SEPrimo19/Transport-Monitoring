@extends('layouts.app')

@section('title', 'Add schedule')
@section('heading', 'Add schedule')
@section('nav_schedules', 'active')

@section('content')
    <div class="row">
        <div class="col-12 col-lg-9">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <form method="POST" action="{{ route('schedules.store') }}" novalidate>
                        @csrf
                        @include('schedules._form')
                        <div class="d-flex gap-2 mt-3 form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i>Save schedule
                            </button>
                            <a href="{{ route('schedules.index') }}" class="btn btn-light">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
            <p class="text-muted small mt-2 mb-0">
                <i class="bi bi-info-circle me-1"></i>
                A schedule is an informational timetable — it doesn't create trips; drivers still start trips from their assignment.
            </p>
        </div>
    </div>
@endsection
