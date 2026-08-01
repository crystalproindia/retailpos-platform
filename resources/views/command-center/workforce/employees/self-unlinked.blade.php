@extends('layouts.admin')

@section('title', 'My workforce profile')
@section('page-title', 'My profile')

@section('content')
    <div class="mx-auto max-w-3xl space-y-6">
        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-sm font-medium text-teal-700">Workforce profile</p>
            <h1 class="mt-2 text-2xl font-semibold text-slate-950">Your employee profile is not linked yet</h1>
            <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-600">Your existing account remains active and unchanged. A Workforce administrator can link it to an employee profile when your team is ready to manage workforce information here.</p>
        </section>
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between gap-3"><div><h2 class="font-semibold text-slate-950">My tasks</h2><p class="mt-1 text-sm text-slate-500">Tasks are available even while a workforce profile is not linked.</p></div><div class="flex items-center gap-3"><a href="{{ route('tasks.index') }}#quick-add" class="text-sm font-semibold text-teal-700">Add task</a><a href="{{ route('tasks.today') }}" class="text-sm font-semibold text-teal-700">Open tasks</a></div></div>
            <div class="mt-4 grid gap-3 sm:grid-cols-3"><a href="{{ route('tasks.today') }}" class="rounded-lg bg-sky-50 p-4"><p class="text-xs text-sky-700">Due today</p><p class="mt-1 text-xl font-semibold">{{ $workTaskMetrics['today'] }}</p></a><a href="{{ route('tasks.overdue') }}" class="rounded-lg bg-rose-50 p-4"><p class="text-xs text-rose-700">Overdue work</p><p class="mt-1 text-xl font-semibold">{{ $workTaskMetrics['overdue'] }}</p></a><a href="{{ route('tasks.personal') }}" class="rounded-lg bg-violet-50 p-4"><p class="text-xs text-violet-700">Personal tasks</p><p class="mt-1 text-xl font-semibold">{{ $personalTaskMetrics['today'] + $personalTaskMetrics['upcoming'] + $personalTaskMetrics['overdue'] }}</p></a></div>
        </section>
    </div>
@endsection
