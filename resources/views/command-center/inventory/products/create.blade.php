@extends('layouts.admin')

@section('title', 'New Product')
@section('page-title', 'New Product')
@section('breadcrumbs')
    <span>/</span><a href="{{ route('inventory.dashboard') }}" class="hover:text-slate-950 dark:hover:text-white">Inventory</a><span>/</span><a href="{{ route('inventory.products.index') }}" class="hover:text-slate-950 dark:hover:text-white">Products</a><span>/</span><span>Create</span>
@endsection

@section('content')
    @include('command-center.inventory.partials.nav')
    <form method="POST" action="{{ route('inventory.products.store') }}" class="inventory-product-workspace">
        @include('command-center.inventory.products._form')
    </form>
@endsection
