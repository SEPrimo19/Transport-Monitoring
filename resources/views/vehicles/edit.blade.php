@extends('layouts.app')

@section('title', 'Edit vehicle')
@section('heading', 'Edit vehicle')
@section('nav_vehicles', 'active')

@section('content')
    <div class="row">
        <div class="col-12 col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <form method="POST" action="{{ route('vehicles.update', $vehicle) }}" novalidate>
                        @csrf
                        @method('PUT')
                        @include('vehicles._form')
                        <div class="d-flex gap-2 mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i>Update vehicle
                            </button>
                            <a href="{{ route('vehicles.index') }}" class="btn btn-light">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
