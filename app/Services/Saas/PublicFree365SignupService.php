<?php

namespace App\Services\Saas;

use App\Contracts\Saas\MobileOtpSender;
use App\Mail\PublicSignupVerificationCode;
use App\Models\SaasPlan;
use App\Models\SaasPublicSignupSession;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PublicFree365SignupService
{
    public function __construct(
        private readonly IndustryRegistry $industries,
        private readonly TenantProvisioningService $provisioning,
        private readonly AuditLogger $audit,
    ) {}

    /** @return array{session: SaasPublicSignupSession, token: string} */
    public function begin(string $industry, string $method, string $destination, string $ip, ?string $userAgent): array
    {
        $this->assertMethodAvailable($method);
        $this->industries->selectable($industry);

        $contact = $this->normalizeDestination($method, $destination);
        $this->assertAvailableContact($method, $contact);
        $this->throttleContact($method, $contact);

        $token = Str::random(64);
        $code = (string) random_int(100000, 999999);
        $session = SaasPublicSignupSession::create([
            'public_token_hash' => hash('sha256', $token),
            'idempotency_key' => (string) Str::uuid(),
            'industry_key' => $industry,
            'verification_method' => $method,
            'email' => $method === 'email' ? $contact : null,
            'mobile' => $method === 'mobile' ? $contact : null,
            'verification_code_hash' => Hash::make($code),
            'verification_expires_at' => now()->addMinutes((int) config('saas.verification.code_ttl_minutes', 10)),
            'resend_available_at' => now()->addSeconds((int) config('saas.verification.resend_cooldown_seconds', 60)),
            'verification_max_attempts' => (int) config('saas.verification.max_attempts', 5),
            'expires_at' => now()->addMinutes((int) config('saas.public_signup.session_ttl_minutes', 30)),
            'started_ip_hash' => hash('sha256', $ip),
            'user_agent_hash' => $userAgent ? hash('sha256', $userAgent) : null,
            'started_at' => now(),
        ]);

        $this->deliver($session, $code);
        $this->audit->record('saas.public_signup.started', $session, 'Public Free 365 signup started.', [
            'verification_method' => $method,
            'industry' => $industry,
            'signup_token_fingerprint' => $this->fingerprint($token),
        ]);

        return compact('session', 'token');
    }

    public function find(string $token): SaasPublicSignupSession
    {
        $session = SaasPublicSignupSession::query()->where('public_token_hash', hash('sha256', $token))->first();
        if (! $session || $session->isExpired()) {
            throw ValidationException::withMessages(['signup' => 'This signup session has expired. Start again to continue.']);
        }

        return $session;
    }

    public function resend(string $token): SaasPublicSignupSession
    {
        return DB::transaction(function () use ($token): SaasPublicSignupSession {
            $session = $this->locked($token);
            if ($session->resend_available_at?->isFuture()) {
                throw ValidationException::withMessages(['verification' => 'Please wait before requesting another code.']);
            }

            $code = (string) random_int(100000, 999999);
            $session->update([
                'verification_code_hash' => Hash::make($code),
                'verification_attempts' => 0,
                'verification_expires_at' => now()->addMinutes((int) config('saas.verification.code_ttl_minutes', 10)),
                'resend_available_at' => now()->addSeconds((int) config('saas.verification.resend_cooldown_seconds', 60)),
            ]);
            $this->deliver($session, $code);
            $this->audit->record('saas.public_signup.otp_resent', $session, 'Public signup verification code resent.', ['verification_method' => $session->verification_method]);

            return $session->refresh();
        });
    }

    public function verify(string $token, string $code): SaasPublicSignupSession
    {
        return DB::transaction(function () use ($token, $code): SaasPublicSignupSession {
            $session = $this->locked($token);
            if ($session->verified_at) return $session;
            if (! $session->verification_code_hash || ! $session->verification_expires_at || $session->verification_expires_at->isPast() || $session->verification_attempts >= $session->verification_max_attempts) {
                throw ValidationException::withMessages(['code' => 'This verification code is no longer valid.']);
            }

            $session->increment('verification_attempts');
            if (! Hash::check($code, $session->verification_code_hash)) {
                throw ValidationException::withMessages(['code' => 'The verification code is incorrect.']);
            }

            $this->assertAvailableContact($session->verification_method, $session->email ?: (string) $session->mobile, $session->id);
            $session->update(['verified_at' => now(), 'verification_code_hash' => null]);
            $this->audit->record('saas.public_signup.otp_verified', $session, 'Public signup verification completed.', ['verification_method' => $session->verification_method]);

            return $session->refresh();
        });
    }

    /** @param array{name:string,password:string,company_name?:string,terms:bool,honeypot?:string,timezone?:string} $data */
    public function complete(string $token, array $data): SaasPublicSignupSession
    {
        return DB::transaction(function () use ($token, $data): SaasPublicSignupSession {
            $session = $this->locked($token);
            if ($session->provisioned_at) return $session;
            if (! $session->verified_at) {
                throw ValidationException::withMessages(['verification' => 'Verify your email or mobile number before creating your store.']);
            }
            if (filled($data['honeypot'] ?? null)) {
                $this->audit->record('saas.public_signup.suspicious_request', $session, 'Public signup honeypot triggered.');
                throw ValidationException::withMessages(['signup' => 'We could not complete this signup. Please try again.']);
            }
            $this->assertAvailableContact($session->verification_method, $session->email ?: (string) $session->mobile, $session->id);

            $plan = SaasPlan::query()->where('code', config('saas.free365_plan_code'))->where('status', 'active')->firstOrFail();
            $onboarding = $this->provisioning->provision([
                'idempotency_key' => $session->idempotency_key,
                'owner_name' => $data['name'],
                'email' => $session->email,
                'mobile' => $session->mobile,
                'password' => $data['password'],
                'company_name' => $data['company_name'] ?? null,
                'industry' => $session->industry_key,
                'saas_plan_id' => $plan->id,
                'timezone' => $data['timezone'] ?? 'Asia/Kolkata',
                'country' => 'India',
                'signup_source' => 'public',
                'verification_completed' => true,
            ], null);

            $session->update([
                'saas_tenant_onboarding_id' => $onboarding->id,
                'provisioned_at' => now(),
                'consent_accepted_at' => now(),
                'terms_version' => config('saas.public_signup.terms_version'),
                'privacy_version' => config('saas.public_signup.privacy_version'),
            ]);
            $this->audit->record('saas.public_signup.completed', $session, 'Public Free 365 signup completed.', [
                'company_id' => $onboarding->company_id,
                'industry' => $session->industry_key,
                'duration_seconds' => max(0, now()->diffInSeconds($session->started_at ?? $session->created_at)),
            ]);

            return $session->refresh();
        });
    }

    /** @return array<string, bool> */
    public function methods(): array
    {
        return ['email' => $this->isMethodAvailable('email'), 'mobile' => $this->isMethodAvailable('mobile')];
    }

    public function normalizeMobile(string $mobile): string
    {
        $digits = preg_replace('/\D+/', '', $mobile) ?? '';
        if (str_starts_with($digits, '00')) $digits = substr($digits, 2);
        if (strlen($digits) === 10) $digits = '91'.$digits;
        if (strlen($digits) < 8 || strlen($digits) > 15) throw ValidationException::withMessages(['mobile' => 'Enter a valid mobile number with country code.']);
        return '+'.$digits;
    }

    private function locked(string $token): SaasPublicSignupSession
    {
        $session = SaasPublicSignupSession::query()->where('public_token_hash', hash('sha256', $token))->lockForUpdate()->first();
        if (! $session || $session->isExpired()) throw ValidationException::withMessages(['signup' => 'This signup session has expired. Start again to continue.']);
        return $session;
    }

    private function normalizeDestination(string $method, string $destination): string
    {
        return $method === 'email' ? mb_strtolower(trim($destination)) : $this->normalizeMobile($destination);
    }

    private function assertMethodAvailable(string $method): void
    {
        if (! $this->isMethodAvailable($method)) throw ValidationException::withMessages(['verification_method' => 'That verification method is not available right now.']);
    }

    private function isMethodAvailable(string $method): bool
    {
        return match ($method) {
            'email' => (bool) config('saas.public_signup.email_otp_enabled'),
            'mobile' => (bool) config('saas.public_signup.mobile_otp_enabled')
                && filled(config('saas.public_signup.mobile_otp_provider'))
                && app()->bound(MobileOtpSender::class)
                && app(MobileOtpSender::class)->isConfigured(),
            default => false,
        };
    }

    private function assertAvailableContact(string $method, string $contact, ?int $exceptSignupId = null): void
    {
        $query = User::query();
        $method === 'email' ? $query->where('email', $contact) : $query->where('mobile', $contact);
        if ($query->exists()) throw ValidationException::withMessages(['contact' => 'We could not continue with this contact. Log in, reset your password, or contact support.']);

        $signup = SaasPublicSignupSession::query()
            ->where($method, $contact)
            ->where(fn ($query) => $query->whereNotNull('verified_at')->orWhereNotNull('provisioned_at'))
            ->where('expires_at', '>', now())
            ->when($exceptSignupId, fn ($query) => $query->whereKeyNot($exceptSignupId));
        if ($signup->exists()) throw ValidationException::withMessages(['contact' => 'We could not continue with this contact. Log in, reset your password, or contact support.']);
    }

    private function throttleContact(string $method, string $contact): void
    {
        $key = 'saas-public-signup:'.hash('sha256', $method.'|'.$contact);
        if (RateLimiter::tooManyAttempts($key, 3)) throw ValidationException::withMessages(['verification' => 'Please wait before requesting another code.']);
        RateLimiter::hit($key, 600);
    }

    private function deliver(SaasPublicSignupSession $session, string $code): void
    {
        if ($session->verification_method === 'email') {
            Mail::to($session->email)->send(new PublicSignupVerificationCode($code));
            return;
        }

        if (! $this->isMethodAvailable('mobile')) throw ValidationException::withMessages(['verification_method' => 'Mobile verification is not configured yet.']);
        app(MobileOtpSender::class)->send((string) $session->mobile, $code);
    }

    private function fingerprint(string $token): string
    {
        return substr(hash('sha256', $token), 0, 16);
    }
}
