<?php

namespace App\Http\Controllers\CommandCenter\Promotions;

use App\Enums\Promotions\CampaignType;
use App\Enums\Promotions\PromotionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Promotions\PromotionCampaignRequest;
use App\Repositories\Promotions\PromotionCampaignRepository;
use App\Services\Promotions\PromotionCampaignService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PromotionCampaignController extends Controller
{
    public function index(Request $request, PromotionCampaignRepository $campaigns): View { return view('command-center.promotions.campaigns.index', ['campaigns' => $campaigns->paginateForCompany($request->user()->company_id, $request->only(['search', 'status', 'trashed'])), 'statuses' => PromotionStatus::cases()]); }
    public function create(): View { return view('command-center.promotions.campaigns.create', ['campaignTypes' => CampaignType::cases(), 'statuses' => PromotionStatus::cases()]); }
    public function store(PromotionCampaignRequest $request, PromotionCampaignService $service): RedirectResponse { $campaign = $service->create($request->user(), $request->validated()); return redirect()->route('promotions.campaigns.show', $campaign)->with('status', 'Campaign created.'); }
    public function show(Request $request, PromotionCampaignRepository $campaigns, int $campaign): View { return view('command-center.promotions.campaigns.show', ['campaign' => $campaigns->findForCompany($request->user()->company_id, $campaign)]); }
    public function edit(Request $request, PromotionCampaignRepository $campaigns, int $campaign): View { return view('command-center.promotions.campaigns.edit', ['campaign' => $campaigns->findForCompany($request->user()->company_id, $campaign), 'campaignTypes' => CampaignType::cases(), 'statuses' => PromotionStatus::cases()]); }
    public function update(PromotionCampaignRequest $request, PromotionCampaignRepository $campaigns, PromotionCampaignService $service, int $campaign): RedirectResponse { $campaign = $service->update($campaigns->findForCompany($request->user()->company_id, $campaign), $request->user(), $request->validated()); return redirect()->route('promotions.campaigns.show', $campaign)->with('status', 'Campaign updated.'); }
    public function destroy(Request $request, PromotionCampaignRepository $campaigns, PromotionCampaignService $service, int $campaign): RedirectResponse { $service->delete($campaigns->findForCompany($request->user()->company_id, $campaign)); return redirect()->route('promotions.campaigns.index')->with('status', 'Campaign deleted.'); }
    public function restore(Request $request, PromotionCampaignRepository $campaigns, PromotionCampaignService $service, int $campaign): RedirectResponse { $campaign = $service->restore($campaigns->findForCompany($request->user()->company_id, $campaign, true)); return redirect()->route('promotions.campaigns.show', $campaign)->with('status', 'Campaign restored.'); }
}
