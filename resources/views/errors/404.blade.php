@extends('errors.minimal')
@section('code', 'Error 404')
@section('title', 'Page not found')
@section('icon', 'bi-signpost-split')
@section('message', 'That page does not exist, or the record it pointed to has since been removed.')
@section('action')
    @include('errors._action')
@endsection
