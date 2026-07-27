<?php

namespace App\Http\Controllers\CommandCenter;

use App\Http\Controllers\Controller;
use App\Services\Saas\Free365OnboardingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class Free365OnboardingController extends Controller
{
    public function dismiss(Request $request, Free365OnboardingService $onboarding): RedirectResponse
    {
        abort_unless($request->user()->isAdministrator(), 403);
        $onboarding->dismiss($request->user());
        return back()->with('status', 'Onboarding checklist dismissed. You can still complete these steps anytime.');
    }
}
