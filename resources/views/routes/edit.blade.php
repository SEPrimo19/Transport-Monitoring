@extends('layouts.app')

@section('title', 'Edit route')
@section('heading', 'Edit route')
@section('nav_routes', 'active')

@section('content')
    <div class="row">
        <div class="col-12 col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <form method="POST" action="{{ route('routes.update', $route) }}" novalidate>
                        @csrf
                        @method('PUT')
                        @include('routes._form')
                        <div class="d-flex gap-2 mt-3 form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i>Update route
                            </button>
                            <a href="{{ route('routes.index') }}" class="btn btn-light">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card border-0 shadow-sm mt-3">
                <div class="card-body">
                    <h2 class="h6 mb-2"><i class="bi bi-bezier2 me-1"></i>Polyline</h2>
                    @if ($route->geometry)
                        <p class="mb-2">
                            This route has a stored polyline of
                            <strong>{{ number_format($route->geometry->point_count) }}</strong> points
                            @if ($route->geometry->source)<span class="text-muted">(source: {{ $route->geometry->source }})</span>@endif.
                            The mobile app reads this for the on-map route overlay.
                        </p>
                    @else
                        <p class="text-muted mb-2">No polyline stored for this route yet.</p>
                    @endif
                    <a href="{{ route('routes.geometry.edit', $route) }}" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-geo-alt me-1"></i>{{ $route->geometry ? 'Edit polyline on map' : 'Draw polyline on map' }}
                    </a>
                </div>
            </div>
        </div>
    </div>
@endsection
