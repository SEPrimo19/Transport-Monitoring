@extends('errors.minimal')
@section('code', 'Error 503')
@section('title', 'Temporarily unavailable')
@section('icon', 'bi-tools')
@section('message', 'The console is down for maintenance and will be back shortly. Live tracking on the mobile app is unaffected.')
@section('action')
    @include('errors._action')
@endsection
