@extends('layouts.admin')

@section('title', 'New CRM Lead')
@section('page-title', 'New CRM Lead')

@section('breadcrumbs')
    <span>/</span><span>CRM</span><span>/</span><span>Leads</span><span>/</span><span>Create</span>
@endsection

@section('content')
    <div class="space-y-6">
        @include('command-center.crm.partials.nav')
        <form method="POST" action="{{ route('crm.leads.store') }}">
            @include('command-center.crm.leads._form')
        </form>
    </div>
@endsection
