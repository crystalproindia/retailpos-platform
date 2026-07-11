@extends('layouts.admin')

@section('title', 'Edit Product')
@section('page-title', 'Edit Product')
@section('breadcrumbs')
    <span>/</span><a href="{{ route('inventory.dashboard') }}" class="hover:text-slate-950 dark:hover:text-white">Inventory</a><span>/</span><a href="{{ route('inventory.products.index') }}" class="hover:text-slate-950 dark:hover:text-white">Products</a><span>/</span><span>Edit</span>
@endsection

@section('content')
    @include('command-center.inventory.partials.nav')
    <form method="POST" action="{{ route('inventory.products.update', $product) }}">
        @include('command-center.inventory.products._form')
    </form>
@endsection
