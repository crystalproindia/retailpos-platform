<?php

namespace App\Http\Controllers\CommandCenter\Promotions;

use App\Enums\Promotions\DiscountType;
use App\Enums\Promotions\PromotionActionType;
use App\Enums\Promotions\PromotionStatus;
use App\Enums\Promotions\PromotionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Promotions\PromotionRuleRequest;
use App\Models\Branch;
use App\Models\Inventory\InventoryBrand;
use App\Models\Inventory\InventoryCategory;
use App\Models\Inventory\Product;
use App\Models\Inventory\SalesChannel;
use App\Repositories\Promotions\PromotionCampaignRepository;
use App\Repositories\Promotions\PromotionRuleRepository;
use App\Services\Promotions\PromotionRuleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PromotionRuleController extends Controller
{
    public function index(Request $request, PromotionRuleRepository $rules): View { return view('command-center.promotions.rules.index', ['rules' => $rules->paginateForCompany($request->user()->company_id, $request->only(['search', 'status', 'promotion_type', 'trashed'])), 'statuses' => PromotionStatus::cases(), 'types' => PromotionType::cases()]); }
    public function create(Request $request, PromotionCampaignRepository $campaigns): View { return view('command-center.promotions.rules.create', $this->formData($request, $campaigns)); }
    public function store(PromotionRuleRequest $request, PromotionRuleService $service): RedirectResponse { $rule = $service->create($request->user(), $request->validated()); return redirect()->route('promotions.rules.show', $rule)->with('status', 'Promotion rule created.'); }
    public function show(Request $request, PromotionRuleRepository $rules, int $rule): View { return view('command-center.promotions.rules.show', ['rule' => $rules->findForCompany($request->user()->company_id, $rule)]); }
    public function edit(Request $request, PromotionRuleRepository $rules, PromotionCampaignRepository $campaigns, int $rule): View { return view('command-center.promotions.rules.edit', ['rule' => $rules->findForCompany($request->user()->company_id, $rule)] + $this->formData($request, $campaigns)); }
    public function update(PromotionRuleRequest $request, PromotionRuleRepository $rules, PromotionRuleService $service, int $rule): RedirectResponse { $rule = $service->update($rules->findForCompany($request->user()->company_id, $rule), $request->user(), $request->validated()); return redirect()->route('promotions.rules.show', $rule)->with('status', 'Promotion rule updated.'); }
    public function destroy(Request $request, PromotionRuleRepository $rules, PromotionRuleService $service, int $rule): RedirectResponse { $service->delete($rules->findForCompany($request->user()->company_id, $rule)); return redirect()->route('promotions.rules.index')->with('status', 'Promotion rule deleted.'); }
    public function restore(Request $request, PromotionRuleRepository $rules, PromotionRuleService $service, int $rule): RedirectResponse { $rule = $service->restore($rules->findForCompany($request->user()->company_id, $rule, true)); return redirect()->route('promotions.rules.show', $rule)->with('status', 'Promotion rule restored.'); }
    public function activate(Request $request, PromotionRuleRepository $rules, PromotionRuleService $service, int $rule): RedirectResponse { $service->activate($rules->findForCompany($request->user()->company_id, $rule), $request->user()); return back()->with('status', 'Promotion activated.'); }
    public function pause(Request $request, PromotionRuleRepository $rules, PromotionRuleService $service, int $rule): RedirectResponse { $service->pause($rules->findForCompany($request->user()->company_id, $rule), $request->user()); return back()->with('status', 'Promotion paused.'); }
    public function approve(Request $request, PromotionRuleRepository $rules, PromotionRuleService $service, int $rule): RedirectResponse { $service->approve($rules->findForCompany($request->user()->company_id, $rule), $request->user()); return back()->with('status', 'Promotion approved.'); }

    /** @return array<string, mixed> */
    private function formData(Request $request, PromotionCampaignRepository $campaigns): array
    {
        $company = $request->user()->company_id;
        return ['campaigns' => $campaigns->paginateForCompany($company, [], 100), 'products' => Product::query()->where('company_id', $company)->where('is_active', true)->orderBy('name')->get(), 'categories' => InventoryCategory::query()->where('company_id', $company)->where('is_active', true)->orderBy('name')->get(), 'brands' => InventoryBrand::query()->where('company_id', $company)->where('is_active', true)->orderBy('name')->get(), 'branches' => Branch::query()->where('company_id', $company)->where('is_active', true)->orderBy('name')->get(), 'channels' => SalesChannel::query()->where('company_id', $company)->where('is_active', true)->orderBy('name')->get(), 'types' => PromotionType::cases(), 'discountTypes' => DiscountType::cases(), 'actionTypes' => PromotionActionType::cases(), 'statuses' => PromotionStatus::cases()];
    }
}
