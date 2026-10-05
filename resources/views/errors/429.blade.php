@extends('errors.minimal')
@section('code', 'Error 429')
@section('title', 'Too many requests')
@section('icon', 'bi-stopwatch')
{{-- The sign-in route redirects back with a message instead of reaching this page
     (see bootstrap/app.php). This covers every other throttled route. --}}
@section('message', 'Too many requests arrived from this connection in a short time. Please wait about a minute, then try again.')
@section('action')
    @include('errors._action')
@endsection
