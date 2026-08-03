<?php

namespace App\Http\Controllers\CommandCenter;

use App\Http\Controllers\Controller;
use App\Models\Ai\AiInsight;
use App\Models\Setting;
use App\Services\Ai\AiForecastService;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class AiForecastController extends Controller
{
    public function index(Request $request, AiForecastService $forecasts): View
    {
        abort_unless($request->user()->can('ai.dashboard.view'), 403);
        return view('command-center.ai.index', ['data' => $forecasts->dashboard($request->user()->company_id), 'settings' => $this->forecastSettings($request->user()->company_id)]);
    }

    public function run(Request $request, AiForecastService $forecasts): RedirectResponse
    {
        abort_unless($request->user()->can('ai.forecasts.run'), 403);
        $key = 'ai-forecast-run:'.$request->user()->id;
        if (RateLimiter::tooManyAttempts($key, 3)) return back()->withErrors(['forecast' => 'Please wait before requesting another recalculation.']);
        RateLimiter::hit($key, 300);
        $type = $request->validate(['type' => ['nullable', 'in:all,sales,inventory,customers,crm']])['type'] ?? 'all';
        $forecasts->run($request->user()->company_id, $type, $request->user());
        return back()->with('status', 'Explainable forecast refresh completed. Results remain advisory until reviewed.');
    }

    public function review(Request $request, AiInsight $insight, AiForecastService $forecasts): RedirectResponse
    {
        abort_unless($request->user()->can('ai.insights.review'), 403);
        $status = $request->validate(['status' => ['required', 'in:reviewed,dismissed,actioned']])['status'];
        $forecasts->review($insight, $request->user(), $status);
        return back()->with('status', 'Insight updated.');
    }

    public function settings(Request $request): View
    {
        abort_unless($request->user()->can('ai.settings.manage'), 403);
        return view('command-center.ai.settings', ['settings' => $this->forecastSettings($request->user()->company_id)]);
    }

    public function updateSettings(Request $request, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->can('ai.settings.manage'), 403);
        $data = $request->validate(['minimum_sales_history_days' => ['required', 'integer', 'min:7', 'max:365'], 'safety_stock_days' => ['required', 'integer', 'min:0', 'max:180'], 'slow_moving_days' => ['required', 'integer', 'min:7', 'max:730'], 'dead_stock_days' => ['required', 'integer', 'min:30', 'max:1095'], 'scheduled_generation_enabled' => ['nullable', 'boolean']]);
        Setting::updateOrCreate(['company_id' => $request->user()->company_id, 'group' => 'ai_forecasting', 'key' => 'settings'], ['value' => $data + ['scheduled_generation_enabled' => $request->boolean('scheduled_generation_enabled')]]);
        $audit->record('ai.settings.updated', null, 'AI forecasting settings updated', ['company_id' => $request->user()->company_id]);
        return back()->with('status', 'Future forecast settings updated. Existing records were not changed.');
    }

    private function forecastSettings(int $companyId): array
    {
        return (Setting::query()->where('company_id', $companyId)->where('group', 'ai_forecasting')->where('key', 'settings')->value('value') ?? []) + config('ai_forecasting');
    }
}
