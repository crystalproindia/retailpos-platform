@extends('layouts.public-signup')

@section('title', 'Free signup is unavailable')
@section('content')
    <div class="text-center">
        <div class="mx-auto grid size-12 place-items-center rounded-full bg-slate-100 text-lg font-bold text-slate-700">RP</div>
        <h1 class="mt-5 text-2xl font-semibold text-slate-950">Free signup is currently unavailable</h1>
        <p class="mt-3 text-sm leading-6 text-slate-500">We are preparing RetailPOS for new stores. Please check back shortly or contact our team for help.</p>
        <a href="{{ route('login') }}" class="mt-7 inline-flex rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white">Go to login</a>
    </div>
@endsection
