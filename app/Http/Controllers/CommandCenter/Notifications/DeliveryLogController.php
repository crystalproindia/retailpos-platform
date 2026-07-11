<?php

namespace App\Http\Controllers\CommandCenter\Notifications;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Repositories\Notifications\DeliveryLogRepository;
use App\Repositories\Notifications\WebhookRepository;
use App\Support\Events\EventCatalog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DeliveryLogController extends Controller
{
    public function __invoke(Request $request, DeliveryLogRepository $deliveryRepository, WebhookRepository $webhookRepository, EventCatalog $eventCatalog): View
    {
        return view('command-center.notifications.deliveries.index', [
            'deliveries' => $deliveryRepository->paginateForUser($request->user(), $request->only(['channel', 'status', 'event_key', 'user_id'])),
            'webhookDeliveries' => $webhookRepository->paginateDeliveries($request->user()),
            'eventOptions' => $eventCatalog->all(),
            'users' => User::query()->where('company_id', $request->user()->company_id)->orderBy('name')->get(),
        ]);
    }
}
