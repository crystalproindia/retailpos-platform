<?php

namespace App\Http\Controllers\CommandCenter;

use App\Http\Controllers\Controller;
use App\Services\Navigation\NavigationPreferenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NavigationPreferenceController extends Controller
{
    public function edit(Request $request, NavigationPreferenceService $preferences): View
    {
        $user = $request->user();

        return view('command-center.navigation.edit', [
            'preference' => $preferences->preferenceFor($user),
            'modules' => $preferences->authorizedModules($user)->groupBy('category'),
            'visibleIds' => $preferences->visibleModules($user)->pluck('id')->all(),
            'pinnedIds' => $preferences->pinnedModules($user)->pluck('id')->all(),
            'hiddenModules' => $preferences->hiddenModules($user),
            'presets' => $preferences->presets(),
        ]);
    }

    public function update(Request $request, NavigationPreferenceService $preferences): RedirectResponse
    {
        $data = $request->validate([
            'visible_module_ids' => ['nullable', 'array'],
            'visible_module_ids.*' => ['string', 'max:100'],
            'pinned_module_ids' => ['nullable', 'array'],
            'pinned_module_ids.*' => ['string', 'max:100'],
            'module_order' => ['nullable', 'array'],
            'module_order.*' => ['string', 'max:100'],
            'selected_preset' => ['nullable', 'string', 'max:40'],
            'apply_preset' => ['nullable', 'boolean'],
        ]);

        if ($request->boolean('apply_preset')) {
            $preferences->applyPreset($request->user(), (string) ($data['selected_preset'] ?? ''));

            return redirect()->route('navigation.preferences.edit')->with('status', 'Navigation preset applied to your authorized modules.');
        }

        $preferences->save($request->user(), $data);

        return redirect()->route('navigation.preferences.edit')->with('status', 'Navigation preferences saved.');
    }

    public function reset(Request $request, NavigationPreferenceService $preferences): RedirectResponse
    {
        $preferences->reset($request->user());

        return redirect()->route('navigation.preferences.edit')->with('status', 'Navigation restored to the recommended defaults.');
    }
}
