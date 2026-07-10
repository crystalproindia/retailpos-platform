@extends('layouts.admin')

@section('title', 'New CRM Contact')
@section('page-title', 'New CRM Contact')

@section('breadcrumbs')
    <span>/</span><span>CRM</span><span>/</span><span>Contacts</span><span>/</span><span>Create</span>
@endsection

@section('content')
    <div class="space-y-6">
        @include('command-center.crm.partials.nav')
        <form method="POST" action="{{ route('crm.contacts.store') }}">
            @include('command-center.crm.contacts._form')
        </form>
    </div>
@endsection
