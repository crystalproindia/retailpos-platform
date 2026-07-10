<?php

namespace App\Http\Controllers\CommandCenter;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateSettingsRequest;
use App\Repositories\SettingsRepository;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function show(Request $request, SettingsRepository $settingsRepository, string $section = 'general'): View
    {
        abort_unless($settingsRepository->exists($section), 404);

        return view('command-center.settings.show', [
            'sections' => $settingsRepository->sections(),
            'section' => $section,
            'activeSection' => $settingsRepository->sections()[$section],
            'values' => $settingsRepository->valuesFor($request->user(), $section),
        ]);
    }

    public function update(
        UpdateSettingsRequest $request,
        SettingsRepository $settingsRepository,
        AuditLogger $auditLogger,
        string $section,
    ): RedirectResponse {
        abort_unless($settingsRepository->exists($section), 404);

        $settingsRepository->updateSection($request->user(), $section, $request->validated());

        $auditLogger->record('settings.updated', null, 'Settings updated', [
            'company_id' => $request->user()->company_id,
            'section' => $section,
            'keys' => array_keys($request->validated()),
        ]);

        return back()->with('status', 'Settings saved.');
    }
}
