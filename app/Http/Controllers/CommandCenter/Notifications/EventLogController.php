<?php

namespace App\Http\Controllers\CommandCenter\Notifications;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Repositories\Notifications\EventLogRepository;
use App\Support\Events\EventCatalog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EventLogController extends Controller
{
    public function __invoke(Request $request, EventLogRepository $eventLogRepository, EventCatalog $eventCatalog): View
    {
        return view('command-center.notifications.events.index', [
            'events' => $eventLogRepository->paginateForUser($request->user(), $request->only(['event_key', 'status', 'actor', 'aggregate_type'])),
            'eventOptions' => $eventCatalog->all(),
            'users' => User::query()->where('company_id', $request->user()->company_id)->orderBy('name')->get(),
        ]);
    }
}
