@extends('layouts.app')

@section('title', 'Edit terminal')
@section('heading', 'Edit terminal')
@section('nav_terminals', 'active')

@section('content')
    <div class="row">
        <div class="col-12 col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <form method="POST" action="{{ route('terminals.update', $terminal) }}" novalidate>
                        @csrf
                        @method('PUT')
                        @include('terminals._form')
                        <div class="d-flex gap-2 mt-3">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i>Update terminal
                            </button>
                            <a href="{{ route('terminals.index') }}" class="btn btn-light">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
