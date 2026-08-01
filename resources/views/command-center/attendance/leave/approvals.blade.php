@extends('layouts.admin')

@section('title', 'Leave approvals')
@section('page-title', 'Leave approvals')

@section('content')
<div class="mx-auto max-w-7xl space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <p class="text-sm font-semibold text-teal-700">Manager workspace</p>
            <h1 class="mt-1 text-2xl font-semibold text-slate-950">Leave approvals</h1>
            <p class="mt-2 text-sm text-slate-500">Requests shown only for your authorized outlets.</p>
        </div>
        @can('leave.manage_policies')
            <details class="rounded-lg border border-slate-300 bg-white px-4 py-2">
                <summary class="cursor-pointer text-sm font-semibold">Add leave policy</summary>
                <form method="POST" action="{{ route('attendance.leave.types.store') }}" class="mt-3 grid gap-2 sm:grid-cols-2">
                    @csrf
                    <input name="name" required placeholder="Policy name" class="rounded border-slate-300 text-sm">
                    <input name="code" required placeholder="Code" class="rounded border-slate-300 text-sm">
                    <input name="annual_entitlement" type="number" step="0.5" value="0" required class="rounded border-slate-300 text-sm">
                    <label class="text-sm"><input type="checkbox" name="is_paid" value="1"> Paid leave</label>
                    <label class="text-sm"><input type="checkbox" name="approval_required" value="1" checked> Needs approval</label>
                    <button class="rounded bg-slate-950 px-3 py-2 text-sm font-semibold text-white">Save policy</button>
                </form>
            </details>
        @endcan
    </div>

    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="divide-y divide-slate-100">
            @forelse($requests as $leave)
                <article class="p-5">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p class="font-semibold text-slate-950">{{ $leave->employee?->display_name }} · {{ $leave->leaveType?->name }}</p>
                            <p class="mt-1 text-sm text-slate-500">{{ $leave->starts_on->format('j M') }} – {{ $leave->ends_on->format('j M Y') }} · {{ $leave->requested_days }} days · {{ $leave->outlet?->name }}</p>
                            @if($leave->reason)
                                <p class="mt-3 text-sm text-slate-600">{{ $leave->reason }}</p>
                            @endif
                        </div>
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold">{{ str($leave->status)->headline() }}</span>
                    </div>
                    @if($leave->status === 'pending')
                        <form method="POST" action="{{ route('attendance.leave.review', $leave) }}" class="mt-4 flex flex-wrap gap-2" onsubmit="return confirm('Confirm this leave request decision?')">
                            @csrf
                            <input name="review_note" placeholder="Reason required for rejection" class="rounded border-slate-300 text-sm">
                            <button name="decision" value="approved" class="rounded bg-emerald-600 px-3 py-2 text-sm font-semibold text-white">Approve</button>
                            <button name="decision" value="rejected" class="rounded border border-rose-300 px-3 py-2 text-sm font-semibold text-rose-700">Reject</button>
                        </form>
                    @endif
                </article>
            @empty
                <div class="p-10 text-center text-sm text-slate-500">No leave requests are available in your authorized scope.</div>
            @endforelse
        </div>
    </section>
    {{ $requests->links() }}
</div>
@endsection
