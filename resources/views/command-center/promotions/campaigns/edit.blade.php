@extends('layouts.admin')
@section('title','Edit Campaign') @section('page-title','Edit Campaign')
@section('breadcrumbs')<span>/</span><a href="{{ route('promotions.dashboard') }}">Promotions</a><span>/</span><a href="{{ route('promotions.campaigns.index') }}">Campaigns</a><span>/</span><span>{{ $campaign->name }}</span>@endsection
@section('content')
@include('command-center.promotions.partials.nav')
<form method="POST" action="{{ route('promotions.campaigns.update', $campaign) }}" class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">@csrf @method('PUT') @include('command-center.promotions.campaigns.form')<div class="mt-6 flex gap-3"><a href="{{ route('promotions.campaigns.show', $campaign) }}" class="rounded-lg border px-4 py-2 text-sm font-semibold">Cancel</a><button class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white">Save changes</button></div></form>
@endsection
