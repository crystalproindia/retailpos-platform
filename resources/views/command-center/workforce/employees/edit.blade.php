@extends('layouts.admin')
@section('title', 'Edit employee')
@section('page-title', 'Edit employee')
@section('breadcrumbs')<span>/</span><a href="{{ route('workforce.dashboard') }}">Workforce</a><span>/</span><a href="{{ route('workforce.employees.show', $employee) }}">{{ $employee->display_name }}</a><span>/</span><span>Edit</span>@endsection
@section('content')@include('command-center.workforce.employees._form')@endsection
