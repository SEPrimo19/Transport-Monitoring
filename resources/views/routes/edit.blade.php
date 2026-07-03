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
                        <div class="d-flex gap-2 mt-3">
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
                        <p class="mb-1">
                            This route has a stored polyline of
                            <strong>{{ number_format($route->geometry->point_count) }}</strong> points
                            @if ($route->geometry->source)<span class="text-muted">(source: {{ $route->geometry->source }})</span>@endif.
                        </p>
                        <p class="text-muted small mb-0">
                            Stored as <code>[lat, lng]</code> pairs. Editing the polyline needs a map editor
                            and isn't available here yet — the mobile app reads this for the on-map route overlay.
                        </p>
                    @else
                        <p class="text-muted mb-0">No polyline stored for this route yet.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
