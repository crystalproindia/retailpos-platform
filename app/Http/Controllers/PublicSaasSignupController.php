<?php

namespace App\Http\Controllers;

use App\Models\SaasPublicSignupSession;
use App\Services\Saas\IndustryRegistry;
use App\Services\Saas\PublicFree365SignupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PublicSaasSignupController extends Controller
{
    public function show(Request $request, IndustryRegistry $industries, PublicFree365SignupService $signup): View|RedirectResponse
    {
        if ($request->user()) return redirect()->route('dashboard');
        if (! config('saas.public_signup.enabled')) return view('saas.public-signup.unavailable');

        $record = $this->current($request, $signup);
        if ($record?->provisioned_at) return redirect()->route('saas.public-signup.success');

        return view('saas.public-signup.show', [
            'industries' => $industries->enabled(),
            'methods' => $signup->methods(),
            'signup' => $record,
            'termsUrl' => config('saas.public_signup.terms_url'),
            'privacyUrl' => config('saas.public_signup.privacy_url'),
        ]);
    }

    public function begin(Request $request, PublicFree365SignupService $signup): RedirectResponse
    {
        abort_unless(config('saas.public_signup.enabled'), 404);
        $data = $request->validate([
            'industry' => ['required', 'string', 'max:80'],
            'verification_method' => ['required', Rule::in(['email', 'mobile'])],
            'email' => ['nullable', 'email:rfc', 'max:255', 'required_if:verification_method,email'],
            'mobile' => ['nullable', 'string', 'max:32', 'required_if:verification_method,mobile'],
            'website' => ['nullable', 'max:0'],
        ]);
        if (filled($data['website'] ?? null)) return back()->withErrors(['signup' => 'We could not start this signup. Please try again.']);
        $destination = $data['verification_method'] === 'email' ? (string) $data['email'] : (string) $data['mobile'];
        $result = $signup->begin($data['industry'], $data['verification_method'], $destination, (string) $request->ip(), $request->userAgent());
        $request->session()->regenerate();
        $request->session()->put('saas_public_signup_token', $result['token']);

        return redirect()->route('saas.public-signup.show')->with('status', 'Enter the code we sent to continue.');
    }

    public function verify(Request $request, PublicFree365SignupService $signup): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'digits:6']]);
        $signup->verify($this->token($request), $data['code']);
        return redirect()->route('saas.public-signup.show')->with('status', 'Your contact has been verified.');
    }

    public function resend(Request $request, PublicFree365SignupService $signup): RedirectResponse
    {
        $signup->resend($this->token($request));
        return redirect()->route('saas.public-signup.show')->with('status', 'A new code has been sent.');
    }

    public function complete(Request $request, PublicFree365SignupService $signup): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'terms' => ['accepted'],
            'website' => ['nullable', 'max:0'],
        ]);
        $signup->complete($this->token($request), $data);
        $request->session()->put('saas_public_signup_completed_token', $this->token($request));

        return redirect()->route('saas.public-signup.success');
    }

    public function success(Request $request, PublicFree365SignupService $signup): View|RedirectResponse
    {
        $token = $request->session()->get('saas_public_signup_completed_token');
        if (! is_string($token)) return redirect()->route('saas.public-signup.show');
        $record = $signup->find($token);
        if (! $record->provisioned_at || ! $record->onboarding) return redirect()->route('saas.public-signup.show');
        $record->load(['onboarding.company']);

        return view('saas.public-signup.success', ['signup' => $record, 'company' => $record->onboarding->company]);
    }

    private function token(Request $request): string
    {
        $token = $request->session()->get('saas_public_signup_token');
        abort_unless(is_string($token) && strlen($token) >= 32, 404);
        return $token;
    }

    private function current(Request $request, PublicFree365SignupService $signup): ?SaasPublicSignupSession
    {
        $token = $request->session()->get('saas_public_signup_token');
        if (! is_string($token)) return null;
        try {
            return $signup->find($token);
        } catch (\Illuminate\Validation\ValidationException) {
            $request->session()->forget('saas_public_signup_token');
            return null;
        }
    }
}
