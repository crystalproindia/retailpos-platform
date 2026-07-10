@extends('layouts.admin')

@section('title', 'Edit CRM Lead')
@section('page-title', 'Edit CRM Lead')

@section('breadcrumbs')
    <span>/</span><span>CRM</span><span>/</span><span>Leads</span><span>/</span><span>Edit</span>
@endsection

@section('content')
    <div class="space-y-6">
        @include('command-center.crm.partials.nav')
        <form method="POST" action="{{ route('crm.leads.update', $lead) }}">
            @include('command-center.crm.leads._form', ['method' => 'PUT'])
        </form>
    </div>
@endsection
