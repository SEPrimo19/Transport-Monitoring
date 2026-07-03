@extends('layouts.app')

@section('title', 'Add route')
@section('heading', 'Add route')
@section('nav_routes', 'active')

@section('content')
    <div class="row">
        <div class="col-12 col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <form method="POST" action="{{ route('routes.store') }}" novalidate>
                        @csrf
                        @include('routes._form')
                        <div class="d-flex gap-2 mt-3">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i>Save route
                            </button>
                            <a href="{{ route('routes.index') }}" class="btn btn-light">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
            <p class="text-muted small mt-2 mb-0">
                <i class="bi bi-info-circle me-1"></i>
                The route <strong>polyline</strong> (map geometry) is managed separately and isn't set here.
            </p>
        </div>
    </div>
@endsection
