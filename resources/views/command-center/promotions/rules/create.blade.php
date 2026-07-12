@extends('layouts.admin')
@section('title','New Promotion Rule') @section('page-title','New Promotion Rule')
@section('breadcrumbs')<span>/</span><a href="{{ route('promotions.dashboard') }}">Promotions</a><span>/</span><a href="{{ route('promotions.rules.index') }}">Rules</a><span>/</span><span>New</span>@endsection
@section('content')
@include('command-center.promotions.partials.nav')
<form method="POST" action="{{ route('promotions.rules.store') }}" class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">@csrf @include('command-center.promotions.rules.form')<div class="mt-6 flex gap-3"><a href="{{ route('promotions.rules.index') }}" class="rounded-lg border px-4 py-2 text-sm font-semibold">Cancel</a><button class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white">Create rule</button></div></form>
@endsection
