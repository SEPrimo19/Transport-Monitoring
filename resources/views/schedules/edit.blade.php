@extends('layouts.app')

@section('title', 'Edit schedule')
@section('heading', 'Edit schedule')
@section('nav_schedules', 'active')

@section('content')
    <div class="row">
        <div class="col-12 col-lg-9">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <form method="POST" action="{{ route('schedules.update', $schedule) }}" novalidate>
                        @csrf
                        @method('PUT')
                        @include('schedules._form')
                        <div class="d-flex gap-2 mt-3">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i>Update schedule
                            </button>
                            <a href="{{ route('schedules.index') }}" class="btn btn-light">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
