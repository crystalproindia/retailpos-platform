<?php

namespace App\Http\Controllers\CommandCenter\Crm;

use App\Http\Controllers\Controller;
use App\Repositories\Crm\ActivityRepository;
use App\Services\Crm\CrmPipelineAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FollowUpController extends Controller
{
    public function __invoke(Request $request, ActivityRepository $activityRepository, CrmPipelineAnalyticsService $analytics): View
    {
        $mode = $request->string('mode')->value();
        $mode = in_array($mode, ['today', 'overdue', 'upcoming', 'completed'], true) ? $mode : 'upcoming';

        return view('command-center.crm.followups.index', [
            'activities' => $activityRepository->followUpsForUser($request->user(), $mode),
            'mode' => $mode,
            'stalledByLeadId' => $analytics->stalledLeads($request->user())->keyBy(fn (array $row) => $row['lead']->id),
        ]);
    }
}
