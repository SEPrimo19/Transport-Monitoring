@extends('errors.minimal')
@section('code', 'Error 500')
@section('title', 'Something went wrong')
@section('icon', 'bi-exclamation-octagon')
{{-- No exception message, class, or file path. With APP_DEBUG off this page is what
     stands between an internal fault and whoever happens to be looking at the screen. --}}
@section('message', 'The console hit an unexpected problem and could not finish that request. It has been logged. Please try again in a moment.')
@section('action')
    @include('errors._action')
@endsection
