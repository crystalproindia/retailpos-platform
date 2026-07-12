<?php

namespace App\Http\Controllers\CommandCenter\Promotions;

use App\Http\Controllers\Controller;
use App\Http\Requests\Promotions\PromotionCouponRequest;
use App\Repositories\Promotions\PromotionCouponRepository;
use App\Repositories\Promotions\PromotionRuleRepository;
use App\Services\Promotions\PromotionCouponService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PromotionCouponController extends Controller
{
    public function index(Request $request, PromotionCouponRepository $coupons): View { return view('command-center.promotions.coupons.index', ['coupons' => $coupons->paginateForCompany($request->user()->company_id, $request->only(['search', 'active', 'trashed']))]); }
    public function create(Request $request, PromotionRuleRepository $rules): View { return view('command-center.promotions.coupons.create', ['rules' => $rules->paginateForCompany($request->user()->company_id, [], 100)]); }
    public function store(PromotionCouponRequest $request, PromotionCouponService $service): RedirectResponse { $coupon = $service->create($request->user(), $request->validated()); return redirect()->route('promotions.coupons.edit', $coupon)->with('status', 'Coupon created.'); }
    public function edit(Request $request, PromotionCouponRepository $coupons, PromotionRuleRepository $rules, int $coupon): View { return view('command-center.promotions.coupons.edit', ['coupon' => $coupons->findForCompany($request->user()->company_id, $coupon), 'rules' => $rules->paginateForCompany($request->user()->company_id, [], 100)]); }
    public function update(PromotionCouponRequest $request, PromotionCouponRepository $coupons, PromotionCouponService $service, int $coupon): RedirectResponse { $coupon = $service->update($coupons->findForCompany($request->user()->company_id, $coupon), $request->user(), $request->validated()); return redirect()->route('promotions.coupons.edit', $coupon)->with('status', 'Coupon updated.'); }
    public function toggle(Request $request, PromotionCouponRepository $coupons, PromotionCouponService $service, int $coupon): RedirectResponse { $service->toggle($coupons->findForCompany($request->user()->company_id, $coupon), $request->user()); return back()->with('status', 'Coupon status updated.'); }
}
