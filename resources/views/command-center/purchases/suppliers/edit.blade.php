@extends('layouts.admin')

@section('title', 'Edit Supplier')
@section('page-title', 'Edit Supplier')
@section('breadcrumbs')
    <span>/</span><span>Purchases</span><span>/</span><span>Suppliers</span><span>/</span><span>Edit</span>
@endsection

@section('content')
    @include('command-center.purchases.partials.nav')
    <form method="POST" action="{{ route('purchases.suppliers.update', $supplier) }}" class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        @include('command-center.purchases.suppliers.form')
    </form>
@endsection
