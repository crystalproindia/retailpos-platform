<?php

namespace App\Http\Controllers\CommandCenter;

use App\Contracts\Ai\AiProviderInterface;
use App\Http\Controllers\Controller;
use App\Jobs\Ai\RefreshAiForecastsJob;
use App\Models\Ai\AiInsight;
use App\Models\Setting;
use App\Services\Ai\AiAssistantService;
use App\Services\Ai\AiForecastService;
use App\Services\AuditLogger;
use App\Services\Outlets\OutletAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AiForecastController extends Controller
{
    public function index(Request $request, AiForecastService $forecasts, AiAssistantService $assistant, OutletAccessService $outlets): View
    {
        abort_unless($request->user()->can('ai.dashboard.view'), 403);
        $sessionKey = $this->assistantSessionKey($request);
        $conversationId = (string) $request->session()->get($sessionKey.'.conversation_id', Str::uuid());
        $request->session()->put($sessionKey.'.conversation_id', $conversationId);

        return view('command-center.ai.index', [
            'data' => $forecasts->dashboard($request->user()),
            'brief' => $assistant->brief($request->user()),
            'answer' => $request->session()->get($sessionKey.'.answer'),
            'question' => $request->session()->get($sessionKey.'.question'),
            'outlets' => $outlets->accessibleOutlets($request->user()),
            'canViewAllOutlets' => $outlets->hasCompanyWideAccess($request->user()),
            'providerConfigured' => app(AiProviderInterface::class)->configured(),
            'settings' => $this->forecastSettings($request->user()->company_id),
        ]);
    }

    public function ask(Request $request, AiAssistantService $assistant, OutletAccessService $outlets): RedirectResponse
    {
        abort_unless($request->user()->can('ai.dashboard.view'), 403);
        $data = $request->validate([
            'question' => ['required', 'string', 'max:'.config('ai.max_question_length', 500)],
            'outlet_id' => ['nullable', 'integer'],
        ]);
        $outletId = filled($data['outlet_id'] ?? null) ? (int) $data['outlet_id'] : null;
        if ($outletId !== null && ! $outlets->accessibleOutlets($request->user())->contains('id', $outletId)) {
            abort(403);
        }
        $sessionKey = $this->assistantSessionKey($request);
        $conversationId = (string) $request->session()->get($sessionKey.'.conversation_id', Str::uuid());
        $answer = $assistant->ask($request->user(), $data['question'], $outletId, $request->session()->get($sessionKey.'.previous_intent'), $conversationId);
        $request->session()->put([
            $sessionKey.'.conversation_id' => $conversationId,
            $sessionKey.'.previous_intent' => $answer['intent'],
            $sessionKey.'.answer' => $answer,
            $sessionKey.'.question' => $data['question'],
        ]);

        return redirect()->route('ai.dashboard')->withFragment('conversation');
    }

    public function run(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('ai.forecasts.run'), 403);
        $key = 'ai-forecast-run:'.$request->user()->id;
        if (RateLimiter::tooManyAttempts($key, 3)) {
            return back()->withErrors(['forecast' => 'Please wait before requesting another recalculation.']);
        }
        RateLimiter::hit($key, 300);
        $type = $request->validate(['type' => ['nullable', 'in:all,sales,inventory,customers,crm']])['type'] ?? 'all';
        RefreshAiForecastsJob::dispatch($request->user()->company_id, $type, $request->user()->id);

        return back()->with('status', 'Forecast refresh queued. Existing results remain advisory until the refresh completes.');
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

    private function assistantSessionKey(Request $request): string
    {
        return 'ai_assistant.'.$request->user()->company_id.'.'.$request->user()->id;
    }
}
