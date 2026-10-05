@extends('errors.minimal')
@section('code', 'Error 403')
@section('title', 'Not allowed')
@section('icon', 'bi-shield-lock')
{{-- Says nothing about what is on the other side: whether a record exists, and which
     cooperative it belongs to, are not facts an unauthorised viewer should learn. --}}
@section('message', 'Your account does not have access to that page. If you believe it should, ask an administrator to check your role.')
@section('action')
    @include('errors._action')
@endsection
