<?php

namespace App\Http\Controllers\CommandCenter\Tasks;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tasks\UpdateTaskRuleSettingRequest;
use App\Models\Tasks\TaskRuleSetting;
use App\Services\AuditLogger;
use App\Services\Tasks\TaskRuleRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TaskRuleSettingController extends Controller
{
    public function index(Request $request, TaskRuleRegistry $registry): View
    {
        abort_unless($request->user()->can('tasks.rules.manage'), 403);
        $settings = TaskRuleSetting::query()->where('company_id', $request->user()->company_id)->get()->keyBy('rule_key');

        return view('command-center.tasks.rules', compact('settings', 'registry'));
    }

    public function update(UpdateTaskRuleSettingRequest $request, TaskRuleRegistry $registry, AuditLogger $audit, string $rule): RedirectResponse
    {
        abort_unless($registry->definition($rule), 404);
        $setting = TaskRuleSetting::query()->updateOrCreate(
            ['company_id' => $request->user()->company_id, 'rule_key' => $rule],
            $request->validated() + ['updated_by' => $request->user()->id],
        );
        $audit->record('tasks.rule.updated', $setting, 'Task rule setting updated', ['company_id' => $setting->company_id, 'rule_key' => $rule, 'is_enabled' => $setting->is_enabled]);

        return back()->with('status', 'Task rule saved.');
    }
}
