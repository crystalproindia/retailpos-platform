<?php

namespace App\Http\Controllers\CommandCenter\Integrations;

use App\Http\Controllers\Controller;
use App\Models\NotificationDelivery;
use App\Enums\Notifications\EmailDeliveryStatus;
use App\Services\Notifications\EmailDeliveryService;
use App\Services\Notifications\EmailDeliveryLifecycleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailDeliveryLogController extends Controller
{
    public function index(Request $request): View
    {
        $deliveries = NotificationDelivery::query()->where('company_id', $request->user()->company_id)->where('channel', 'email')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('template'), fn ($query) => $query->where('template_key', $request->string('template')))
            ->when($request->filled('recipient'), fn ($query) => $query->where('recipient', 'like', '%'.$request->string('recipient').'%'))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('created_at', '<=', $request->date('to')))
            ->latest()->paginate(20)->withQueryString();

        return view('command-center.integrations.email.deliveries', compact('deliveries'));
    }

    public function retry(Request $request, EmailDeliveryService $delivery, int $emailDelivery): RedirectResponse
    {
        $record = NotificationDelivery::query()->where('company_id', $request->user()->company_id)->where('channel', 'email')->findOrFail($emailDelivery);
        abort_unless(in_array($record->status, ['temporarily_failed', 'failed'], true), 422, 'Only temporary email delivery failures can be retried.');
        $delivery->retry($record, $request->user());

        return back()->with('status', 'Email delivery queued for retry.');
    }

    public function cancel(Request $request, EmailDeliveryLifecycleService $lifecycle, int $emailDelivery): RedirectResponse
    {
        $record = NotificationDelivery::query()->where('company_id', $request->user()->company_id)->where('channel', 'email')->findOrFail($emailDelivery);
        abort_unless(in_array($record->status, ['pending', 'queued'], true), 422, 'This email can no longer be cancelled.');
        if ($record->status === 'pending') $record->update(['status' => 'queued']);
        $lifecycle->transition($record->fresh(), EmailDeliveryStatus::Cancelled, 'delivery.cancelled');

        return back()->with('status', 'Pending email delivery cancelled.');
    }
}
