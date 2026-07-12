<?php

namespace App\Http\Controllers\CommandCenter\Promotions;

use App\Http\Controllers\Controller;
use App\Models\Promotions\PromotionRuleUsage;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PromotionUsageController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('command-center.promotions.usage.index', ['usage' => PromotionRuleUsage::query()->with('rule')->where('company_id', $request->user()->company_id)->latest('usage_date')->paginate(20)->withQueryString()]);
    }
}
