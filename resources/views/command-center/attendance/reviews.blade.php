@extends('layouts.admin')

@section('title', 'Attendance reviews')
@section('page-title', 'Attendance reviews')

@section('content')
<div class="mx-auto max-w-6xl space-y-6">
    <div>
        <p class="text-sm font-semibold text-teal-700">Manager workspace</p>
        <h1 class="mt-1 text-2xl font-semibold text-slate-950">Corrections and overtime</h1>
        <p class="mt-2 text-sm text-slate-500">Review only the workforce evidence from your authorized outlets.</p>
    </div>

    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 p-5">
            <h2 class="font-semibold text-slate-950">Attendance corrections</h2>
        </div>
        <div class="divide-y divide-slate-100">
            @forelse($corrections as $correction)
                <article class="p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="font-semibold text-slate-950">{{ $correction->employee?->display_name }}</p>
                            <p class="mt-1 text-sm text-slate-500">{{ $correction->attendance?->attendance_date?->format('j M Y') }} · {{ $correction->employee?->primaryBranch?->name }}</p>
                            <p class="mt-3 text-sm text-slate-600">{{ $correction->reason }}</p>
                        </div>
                        <span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-800">Pending review</span>
                    </div>
                    <form method="POST" action="{{ route('attendance.corrections.review', $correction) }}" class="mt-4 flex flex-wrap gap-2" onsubmit="return confirm('Confirm this attendance correction decision?')">
                        @csrf
                        <input name="review_note" placeholder="Reason required for rejection" class="rounded border-slate-300 text-sm">
                        <button name="decision" value="approved" class="rounded bg-emerald-600 px-3 py-2 text-sm font-semibold text-white">Approve</button>
                        <button name="decision" value="rejected" class="rounded border border-rose-300 px-3 py-2 text-sm font-semibold text-rose-700">Reject</button>
                    </form>
                </article>
            @empty
                <div class="p-10 text-center text-sm text-slate-500">No attendance corrections need review.</div>
            @endforelse
        </div>
        {{ $corrections->links() }}
    </section>

    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 p-5">
            <h2 class="font-semibold text-slate-950">Overtime evidence</h2>
        </div>
        <div class="divide-y divide-slate-100">
            @forelse($overtimeReviews as $review)
                <article class="p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="font-semibold text-slate-950">{{ $review->employee?->display_name }}</p>
                            <p class="mt-1 text-sm text-slate-500">{{ $review->attendance?->attendance_date?->format('j M Y') }} · {{ $review->candidate_minutes }} candidate minutes</p>
                        </div>
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">Evidence only</span>
                    </div>
                    <form method="POST" action="{{ route('attendance.overtime.review', $review) }}" class="mt-4 flex flex-wrap items-center gap-2" onsubmit="return confirm('Confirm this overtime review decision?')">
                        @csrf
                        <input name="approved_minutes" type="number" min="0" max="{{ $review->candidate_minutes }}" value="{{ $review->candidate_minutes }}" class="w-28 rounded border-slate-300 text-sm">
                        <input name="reason" placeholder="Reason required for rejection" class="rounded border-slate-300 text-sm">
                        <button name="status" value="approved" class="rounded bg-emerald-600 px-3 py-2 text-sm font-semibold text-white">Approve</button>
                        <button name="status" value="rejected" class="rounded border border-rose-300 px-3 py-2 text-sm font-semibold text-rose-700">Reject</button>
                    </form>
                </article>
            @empty
                <div class="p-10 text-center text-sm text-slate-500">No overtime evidence needs review.</div>
            @endforelse
        </div>
        {{ $overtimeReviews->links() }}
    </section>
</div>
@endsection
