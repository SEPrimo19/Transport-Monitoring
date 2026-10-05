@extends('errors.minimal')
@section('code', 'Error 419')
@section('title', 'Your session expired')
@section('icon', 'bi-hourglass-split')
{{-- Most often a sign-in page left open past the session lifetime. Naming the cause
     matters: "page expired" alone reads as a fault, when the fix is simply to retry. --}}
@section('message', 'This page sat open long enough for its security token to expire. Sign in again and your work can continue.')
@section('action')
    @include('errors._action')
@endsection
