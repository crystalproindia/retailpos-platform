@extends('layouts.admin')

@section('title', 'Edit CRM Company')
@section('page-title', 'Edit CRM Company')

@section('breadcrumbs')
    <span>/</span><span>CRM</span><span>/</span><span>Companies</span><span>/</span><span>Edit</span>
@endsection

@section('content')
    <div class="space-y-6">
        @include('command-center.crm.partials.nav')
        <form method="POST" action="{{ route('crm.companies.update', $crmCompany) }}">
            @include('command-center.crm.companies._form', ['method' => 'PUT'])
        </form>
    </div>
@endsection
