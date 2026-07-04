@extends('layouts.app')

@section('title', 'Add terminal')
@section('heading', 'Add terminal')
@section('nav_terminals', 'active')

@section('content')
    <div class="row">
        <div class="col-12 col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <form method="POST" action="{{ route('terminals.store') }}" novalidate>
                        @csrf
                        @include('terminals._form')
                        <div class="d-flex gap-2 mt-3">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i>Save terminal
                            </button>
                            <a href="{{ route('terminals.index') }}" class="btn btn-light">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
            <p class="text-muted small mt-2 mb-0">
                <i class="bi bi-info-circle me-1"></i>
                Terminals are the endpoints routes attach to. Add both endpoints before creating a route between them.
            </p>
        </div>
    </div>
@endsection
