@extends('layouts.admin')

@section('title', 'New CRM Company')
@section('page-title', 'New CRM Company')

@section('breadcrumbs')
    <span>/</span><span>CRM</span><span>/</span><span>Companies</span><span>/</span><span>Create</span>
@endsection

@section('content')
    <div class="space-y-6">
        @include('command-center.crm.partials.nav')
        <form method="POST" action="{{ route('crm.companies.store') }}">
            @include('command-center.crm.companies._form')
        </form>
    </div>
@endsection
