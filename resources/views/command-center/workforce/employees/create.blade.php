@extends('layouts.admin')
@section('title', 'Add employee')
@section('page-title', 'Add employee')
@section('breadcrumbs')<span>/</span><a href="{{ route('workforce.dashboard') }}">Workforce</a><span>/</span><a href="{{ route('workforce.employees.index') }}">Employees</a><span>/</span><span>Add</span>@endsection
@section('content')@include('command-center.workforce.employees._form')@endsection
