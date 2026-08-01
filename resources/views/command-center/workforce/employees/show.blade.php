@extends('layouts.admin')

@section('title', $employee->display_name)
@section('page-title', 'Employee profile')
@section('breadcrumbs')
    <span>/</span><a href="{{ route('workforce.dashboard') }}">Workforce</a><span>/</span><a href="{{ route('workforce.employees.index') }}">Employees</a><span>/</span><span>{{ $employee->display_name }}</span>
@endsection

@section('content')
    <div class="mx-auto max-w-6xl space-y-6">
        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-sm text-slate-500">{{ $employee->employee_number }}</p>
                    <h1 class="mt-1 text-2xl font-semibold text-slate-950">{{ $employee->display_name }}</h1>
                    <p class="mt-1 text-slate-500">{{ $employee->job_title ?: 'Job title not set' }} · {{ $employee->primaryBranch?->name ?: 'No primary outlet' }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-sm font-medium text-slate-700">{{ str($employee->status)->headline() }}</span>
                    @can('workforce.manage')<a class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-semibold text-slate-700" href="{{ route('workforce.employees.edit', $employee) }}">Edit</a>@endcan
                </div>
            </div>
        </section>

        <div class="grid gap-6 lg:grid-cols-3">
            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm lg:col-span-2">
                <div class="flex items-center justify-between"><h2 class="font-semibold text-slate-950">Operational context</h2><span class="text-xs font-medium text-slate-500">{{ $metrics['period'] }}</span></div>
                @if($metrics['available'])
                    <div class="mt-4 grid gap-3 sm:grid-cols-3">
                        <div class="rounded-lg bg-teal-50 p-4"><p class="text-xs font-medium text-teal-700">Completed sales</p><p class="mt-1 text-xl font-semibold text-teal-950">{{ $metrics['sales_count'] }}</p></div>
                        <div class="rounded-lg bg-slate-50 p-4"><p class="text-xs font-medium text-slate-500">Net sales</p><p class="mt-1 text-xl font-semibold text-slate-950">{{ number_format($metrics['net_sales'] / 100, 2) }}</p></div>
                        <div class="rounded-lg bg-slate-50 p-4"><p class="text-xs font-medium text-slate-500">Average order</p><p class="mt-1 text-xl font-semibold text-slate-950">{{ $metrics['average_order_value'] === null ? 'Not available' : number_format($metrics['average_order_value'] / 100, 2) }}</p></div>
                    </div>
                @else
                    <div class="mt-4 rounded-lg bg-slate-50 p-4 text-sm text-slate-600">Operational metrics are unavailable: {{ $metrics['notice'] }}</div>
                @endif
                <p class="mt-4 text-xs text-slate-500">{{ $metrics['notice'] }}</p>
            </section>
            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="font-semibold text-slate-950">Login access</h2>
                @if($employee->user)
                    <p class="mt-3 break-all text-sm font-medium text-slate-800">{{ $employee->user->email }}</p>
                    <p class="mt-1 text-sm text-slate-500">{{ str($employee->user->account_status)->headline() }} · last login {{ $employee->user->last_login_at?->diffForHumans() ?: 'not yet recorded' }}</p>
                @else
                    <p class="mt-3 text-sm text-slate-500">This employee has no login account.</p>
                    @can('workforce.manage')<a class="mt-4 inline-block rounded-lg bg-slate-950 px-3 py-2 text-sm font-semibold text-white" href="{{ route('workforce.users.create', $employee) }}">Create account</a>@endcan
                @endif
            </section>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="font-semibold text-slate-950">Assignments</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div><dt class="text-slate-500">Outlets</dt><dd class="mt-1 font-medium text-slate-800">{{ $employee->outletAssignments->pluck('branch.name')->filter()->join(', ') ?: 'No outlet assignment' }}</dd></div>
                    <div><dt class="text-slate-500">Warehouses</dt><dd class="mt-1 font-medium text-slate-800">{{ $employee->warehouseAssignments->pluck('warehouse.name')->filter()->join(', ') ?: 'No warehouse assignment' }}</dd></div>
                    <div><dt class="text-slate-500">POS registers</dt><dd class="mt-1 font-medium text-slate-800">{{ $employee->registerAssignments->pluck('register.name')->filter()->join(', ') ?: 'No register assignment' }}</dd></div>
                    <div><dt class="text-slate-500">Reporting manager</dt><dd class="mt-1 font-medium text-slate-800">{{ $employee->manager?->display_name ?: 'Not assigned' }}</dd></div>
                </dl>
            </section>
            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="font-semibold text-slate-950">Invitation history</h2>
                <div class="mt-3 space-y-3 text-sm">
                    @forelse ($employee->invitations->sortByDesc('created_at')->take(3) as $invitation)
                        <div class="flex items-start justify-between gap-3">
                            <span>
                                <span class="block font-medium text-slate-700">{{ $invitation->email }}</span>
                                <span class="text-slate-500">{{ $invitation->accepted_at ? 'Accepted' : ($invitation->cancelled_at ? 'Cancelled' : ($invitation->expires_at->isPast() ? 'Expired' : 'Pending')) }}</span>
                            </span>
                            @if (! $invitation->accepted_at && ! $invitation->cancelled_at && $invitation->expires_at->isFuture())
                                @can('workforce.manage')
                                    <form method="POST" action="{{ route('workforce.invitations.cancel', $invitation) }}">
                                        @csrf
                                        <button class="text-xs font-semibold text-rose-700">Cancel</button>
                                    </form>
                                @endcan
                            @endif
                        </div>
                    @empty
                        <p class="text-slate-500">No activation invitation has been sent.</p>
                    @endforelse
                </div>
            </section>
        </div>

        @can('workforce.manage')
            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="font-semibold text-slate-950">Send secure activation invitation</h2>
                <p class="mt-1 text-sm text-slate-500">The recipient receives a 72-hour, single-use password-setup link. Sending a new invitation replaces an earlier pending link.</p>
                <form method="POST" action="{{ route('workforce.invitations.store', $employee) }}" class="mt-4 grid gap-3 md:grid-cols-4">@csrf
                    <input name="name" value="{{ old('name', $employee->user?->name ?: $employee->display_name) }}" class="rounded-lg border-slate-300 text-sm" aria-label="Account name" placeholder="Account name">
                    <input name="email" type="email" value="{{ old('email', $employee->user?->email ?: $employee->work_email) }}" class="rounded-lg border-slate-300 text-sm" aria-label="Work email" placeholder="Work email" required>
                    <select name="branch_id" class="rounded-lg border-slate-300 text-sm" aria-label="Primary outlet"><option value="{{ $employee->primary_branch_id }}">{{ $employee->primaryBranch?->name ?: 'No primary outlet' }}</option></select>
                    <select name="role" class="rounded-lg border-slate-300 text-sm" aria-label="Base role">@foreach($systemRoles as $role)<option value="{{ $role->value }}" @selected($employee->user?->role === $role)>{{ $role->label() }}</option>@endforeach</select>
                    <button class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white md:col-span-4">Send invitation</button>
                </form>
            </section>
        @endcan

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="font-semibold text-slate-950">Manager reviews</h2>
                <div class="mt-3 space-y-3">
                    @forelse ($employee->reviews->sortByDesc('period_ends_at') as $review)
                        <article class="rounded-lg bg-slate-50 p-3 text-sm"><div class="flex justify-between gap-3"><strong>{{ str($review->cycle)->headline() }} review</strong><span class="text-slate-500">{{ str($review->status)->headline() }}</span></div><p class="mt-1 text-slate-600">{{ $review->period_starts_at->format('d M Y') }} - {{ $review->period_ends_at->format('d M Y') }}</p><p class="mt-2 text-slate-700">{{ $review->comments }}</p></article>
                    @empty
                        <p class="mt-3 text-sm text-slate-500">No manager review is recorded.</p>
                    @endforelse
                </div>
                @can('workforce.reviews.manage')
                    <form method="POST" action="{{ route('workforce.reviews.store', $employee) }}" class="mt-5 grid gap-3 sm:grid-cols-2">@csrf
                        <input type="date" name="period_starts_at" class="rounded-lg border-slate-300 text-sm" required><input type="date" name="period_ends_at" class="rounded-lg border-slate-300 text-sm" required>
                        <select name="cycle" class="rounded-lg border-slate-300 text-sm">@foreach(['monthly', 'quarterly', 'half_yearly', 'annual', 'custom'] as $cycle)<option value="{{ $cycle }}">{{ str($cycle)->headline() }}</option>@endforeach</select><select name="status" class="rounded-lg border-slate-300 text-sm"><option value="draft">Save draft</option><option value="submitted">Submit review</option></select>
                        @foreach (['customer_service' => 'Customer service', 'product_knowledge' => 'Product knowledge', 'teamwork' => 'Teamwork', 'reliability' => 'Reliability', 'communication' => 'Communication', 'initiative' => 'Initiative'] as $field => $label)
                            <label class="text-xs font-medium text-slate-600">{{ $label }}<select name="{{ $field }}" class="mt-1 block w-full rounded-lg border-slate-300 text-sm"><option value="">Not rated</option>@for($score = 1; $score <= 5; $score++)<option value="{{ $score }}">{{ $score }}</option>@endfor</select></label>
                        @endforeach
                        <textarea name="comments" class="rounded-lg border-slate-300 text-sm sm:col-span-2" rows="3" placeholder="Required context for this review" required></textarea><button class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white sm:col-span-2">Save review</button>
                    </form>
                @endcan
            </section>
            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="font-semibold text-slate-950">Recognition</h2>
                <div class="mt-3 space-y-3">
                    @forelse ($employee->recognitions->sortByDesc('recognized_on') as $recognition)
                        <article class="rounded-lg bg-teal-50 p-3 text-sm"><div class="flex justify-between gap-3"><strong class="text-teal-950">{{ $recognition->title }}</strong><span class="text-teal-700">{{ $recognition->recognized_on->format('d M Y') }}</span></div><p class="mt-1 text-teal-800">{{ $recognition->message }}</p></article>
                    @empty
                        <p class="mt-3 text-sm text-slate-500">No recognition has been recorded.</p>
                    @endforelse
                </div>
                @can('workforce.recognition.manage')
                    <form method="POST" action="{{ route('workforce.recognitions.store', $employee) }}" class="mt-5 grid gap-3 sm:grid-cols-2">@csrf
                        <select name="type" class="rounded-lg border-slate-300 text-sm">@foreach(['employee_of_month' => 'Employee of the Month', 'customer_service' => 'Excellent Customer Service', 'sales_achievement' => 'Sales Achievement', 'inventory_accuracy' => 'Inventory Accuracy', 'team_contribution' => 'Team Contribution', 'manager_appreciation' => 'Manager Appreciation', 'custom' => 'Custom recognition'] as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select><input type="date" name="recognized_on" value="{{ now()->toDateString() }}" class="rounded-lg border-slate-300 text-sm" required>
                        <input name="title" class="rounded-lg border-slate-300 text-sm sm:col-span-2" placeholder="Recognition title" required><textarea name="message" class="rounded-lg border-slate-300 text-sm sm:col-span-2" rows="3" placeholder="Optional appreciation message"></textarea><button class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white sm:col-span-2">Record recognition</button>
                    </form>
                @endcan
            </section>
        </div>

        @can('workforce.manage')
            <details class="rounded-xl border border-rose-200 bg-rose-50 p-5"><summary class="cursor-pointer font-semibold text-rose-800">Archive employee</summary><p class="mt-2 text-sm text-rose-700">This preserves historical sales, reviews, and audit records. A linked account is disabled.</p><form method="POST" action="{{ route('workforce.employees.archive', $employee) }}" class="mt-3">@csrf<button class="rounded-lg bg-rose-700 px-4 py-2 text-sm font-semibold text-white">Archive employee</button></form></details>
        @endcan
    </div>
@endsection
