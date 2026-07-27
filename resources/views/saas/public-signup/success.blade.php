@extends('layouts.public-signup')

@section('title', 'Your RetailPOS store is ready')
@section('content')
    <div class="text-center">
        <div class="mx-auto grid size-14 place-items-center rounded-full bg-teal-100 text-2xl font-bold text-teal-800">✓</div>
        <span class="mt-5 inline-flex rounded-full bg-teal-50 px-3 py-1 text-xs font-semibold text-teal-800">Free 365</span>
        <h1 class="mt-4 text-2xl font-semibold text-slate-950">Your RetailPOS store is ready!</h1>
        <p class="mt-3 text-sm leading-6 text-slate-500">Start adding products and create your first invoice today.</p>
        <dl class="mt-7 grid grid-cols-2 gap-3 text-left text-sm"><div class="rounded-lg bg-slate-50 p-3"><dt class="text-slate-500">Store</dt><dd class="mt-1 font-semibold text-slate-950">{{ $company->name }}</dd></div><div class="rounded-lg bg-slate-50 p-3"><dt class="text-slate-500">Industry</dt><dd class="mt-1 font-semibold text-slate-950">{{ str($signup->industry_key)->replace('_',' ')->headline() }}</dd></div><div class="rounded-lg bg-slate-50 p-3"><dt class="text-slate-500">Access</dt><dd class="mt-1 font-semibold text-slate-950">365 days</dd></div><div class="rounded-lg bg-slate-50 p-3"><dt class="text-slate-500">Invoices</dt><dd class="mt-1 font-semibold text-slate-950">25 per month</dd></div></dl>
        <a href="{{ route('login') }}" class="mt-7 flex w-full items-center justify-center rounded-lg bg-slate-950 px-4 py-3 text-sm font-semibold text-white">Open My RetailPOS</a>
    </div>
@endsection
