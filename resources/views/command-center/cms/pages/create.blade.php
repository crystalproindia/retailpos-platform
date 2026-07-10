@extends('layouts.admin')

@section('title', 'Create CMS Page')
@section('page-title', 'Create CMS Page')

@section('breadcrumbs')
    <span>/</span>
    <span>CMS</span>
    <span>/</span>
    <span>Pages</span>
@endsection

@section('content')
    <div class="space-y-6">
        @include('command-center.cms.partials.nav')
        <form method="POST" action="{{ route('cms.pages.store') }}">
            @include('command-center.cms.pages._form', ['method' => 'POST'])
        </form>
    </div>
@endsection
