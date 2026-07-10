<?php

namespace App\Http\Controllers\CommandCenter\Crm;

use App\Http\Controllers\Controller;
use App\Repositories\Crm\ActivityRepository;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FollowUpController extends Controller
{
    public function __invoke(Request $request, ActivityRepository $activityRepository): View
    {
        return view('command-center.crm.followups.index', [
            'activities' => $activityRepository->followUpsForUser($request->user(), $request->boolean('overdue')),
        ]);
    }
}
