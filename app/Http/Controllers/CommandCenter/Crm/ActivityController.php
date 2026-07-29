<?php

namespace App\Http\Controllers\CommandCenter\Crm;

use App\Enums\Crm\ActivityType;
use App\Enums\Crm\LeadPriority;
use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\CompleteActivityRequest;
use App\Http\Requests\Crm\CancelActivityRequest;
use App\Http\Requests\Crm\RescheduleActivityRequest;
use App\Http\Requests\Crm\StoreActivityRequest;
use App\Repositories\Crm\ActivityRepository;
use App\Repositories\Crm\ContactRepository;
use App\Repositories\Crm\CrmCompanyRepository;
use App\Repositories\Crm\LeadRepository;
use App\Services\Crm\ActivityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityController extends Controller
{
    public function index(Request $request, ActivityRepository $activityRepository, LeadRepository $leadRepository, CrmCompanyRepository $companyRepository, ContactRepository $contactRepository): View
    {
        return view('command-center.crm.activities.index', [
            'activities' => $activityRepository->paginateForUser($request->user(), $request->only(['type', 'status', 'trashed'])),
            'leads' => $leadRepository->queryForUser($request->user())->orderBy('title')->limit(100)->get(),
            'crmCompanies' => $companyRepository->optionsForUser($request->user()),
            'contacts' => $contactRepository->optionsForUser($request->user()),
            'types' => ActivityType::cases(),
            'priorities' => LeadPriority::cases(),
        ]);
    }

    public function store(StoreActivityRequest $request, ActivityService $activityService): RedirectResponse
    {
        $activityService->create($request->user(), $request->validated());

        return back()->with('status', 'CRM activity scheduled.');
    }

    public function complete(CompleteActivityRequest $request, ActivityRepository $activityRepository, ActivityService $activityService, int $activity): RedirectResponse
    {
        $activityService->complete(
            $activityRepository->findForUser($request->user(), $activity),
            $request->user(),
            $request->validated('outcome'),
        );

        return back()->with('status', 'CRM activity completed.');
    }

    public function reschedule(RescheduleActivityRequest $request, ActivityRepository $activityRepository, ActivityService $activityService, int $activity): RedirectResponse
    {
        $activityService->reschedule(
            $activityRepository->findForUser($request->user(), $activity),
            $request->validated('scheduled_at'),
        );

        return back()->with('status', 'CRM activity rescheduled.');
    }

    public function cancel(CancelActivityRequest $request, ActivityRepository $activityRepository, ActivityService $activityService, int $activity): RedirectResponse
    {
        $activityService->cancel($activityRepository->findForUser($request->user(), $activity), $request->user(), $request->validated('outcome'));

        return back()->with('status', 'CRM follow-up cancelled.');
    }
}
