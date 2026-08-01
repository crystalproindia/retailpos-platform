@extends('layouts.admin')

@section('title', 'Attendance dashboard')
@section('page-title', 'Attendance dashboard')

@section('content')
<div class="mx-auto max-w-7xl space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <p class="text-sm font-semibold text-teal-700">Workforce coverage</p>
            <h1 class="mt-1 text-2xl font-semibold text-slate-950">Today’s attendance</h1>
            <p class="mt-2 text-sm text-slate-500">Operational evidence for your authorized outlets only.</p>
        </div>
        <div class="flex flex-wrap gap-3 text-sm font-semibold text-teal-700">
            <a href="{{ route('attendance.roster') }}">Weekly roster</a>
            <a href="{{ route('attendance.reviews') }}">Review exceptions</a>
            <a href="{{ route('attendance.export') }}">Export register</a>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
        @foreach(['Present now' => $metrics['present'], 'Late today' => $metrics['late'], 'On break' => $metrics['on_break'], 'Missing check-outs' => $metrics['missing_check_out'], 'Leave approvals' => $pendingLeave, 'Corrections' => $pendingCorrections] as $label => $value)
            <a href="{{ in_array($label, ['Leave approvals', 'Corrections'], true) ? route('attendance.reviews') : route('attendance.index') }}" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-teal-200">
                <p class="text-sm text-slate-500">{{ $label }}</p>
                <p class="mt-2 text-2xl font-semibold text-slate-950">{{ $value }}</p>
            </a>
        @endforeach
    </div>

    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 p-5">
            <h2 class="font-semibold text-slate-950">Exceptions requiring attention</h2>
        </div>
        <div class="divide-y divide-slate-100">
            @forelse($exceptions as $record)
                <a href="{{ route('attendance.index') }}" class="flex items-center justify-between gap-4 p-4 transition hover:bg-slate-50">
                    <span>
                        <strong class="block">{{ $record->employee?->display_name }}</strong>
                        <span class="text-sm text-slate-500">{{ $record->outlet?->name }}</span>
                    </span>
                    <span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-800">{{ str($record->attendance_status)->headline() }}</span>
                </a>
            @empty
                <div class="p-10 text-center text-sm text-slate-500">No missing check-outs or pending corrections right now.</div>
            @endforelse
        </div>
    </section>
</div>
@endsection
