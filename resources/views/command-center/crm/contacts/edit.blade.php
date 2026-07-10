@extends('layouts.admin')

@section('title', 'Edit CRM Contact')
@section('page-title', 'Edit CRM Contact')

@section('breadcrumbs')
    <span>/</span><span>CRM</span><span>/</span><span>Contacts</span><span>/</span><span>Edit</span>
@endsection

@section('content')
    <div class="space-y-6">
        @include('command-center.crm.partials.nav')
        <form method="POST" action="{{ route('crm.contacts.update', $contact) }}">
            @include('command-center.crm.contacts._form', ['method' => 'PUT'])
        </form>
    </div>
@endsection
