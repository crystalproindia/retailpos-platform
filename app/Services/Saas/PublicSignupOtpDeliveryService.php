<?php

namespace App\Services\Saas;

use App\Models\Company;
use App\Models\NotificationDelivery;
use App\Models\SaasPublicSignupSession;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Notifications\EmailDeliveryService;
use Illuminate\Support\Facades\Log;
use Throwable;

class PublicSignupOtpDeliveryService
{
    public const TEMPLATE_KEY = 'saas_public_signup_otp';

    public function __construct(
        private readonly EmailDeliveryService $email,
        private readonly AuditLogger $audit,
    ) {}

    public function queue(SaasPublicSignupSession $session, string $code): ?NotificationDelivery
    {
        if ($session->verification_method !== 'email' || blank($session->email)) {
            return null;
        }

        $company = $this->deliveryCompany();
        if (! $company) {
            $session->update([
                'verification_delivery_id' => null,
                'verification_delivery_status' => 'failed',
            ]);
            $this->audit->record('saas.public_signup.otp_delivery_unavailable', $session, 'Public signup OTP delivery is unavailable.', []);

            return null;
        }

        try {
            $delivery = $this->email->queue(
                companyId: $company->id,
                recipient: (string) $session->email,
                subject: 'Your RetailPOS verification code',
                templateKey: self::TEMPLATE_KEY,
                payload: [
                    'heading' => 'Verify your RetailPOS account',
                    'greeting' => 'Hello,',
                    'message' => 'Use the verification code in this email to continue setting up your free RetailPOS account.',
                    'details' => ['Expires in' => (int) config('saas.verification.code_ttl_minutes', 10).' minutes'],
                ],
                related: $session,
                idempotencyKey: 'saas-signup-otp:'.$session->id.':'.$session->verification_sequence,
                sensitivePayload: [
                    'message' => 'Your RetailPOS verification code is '.$code.".\n\nThis code expires in ".(int) config('saas.verification.code_ttl_minutes', 10).' minutes and can only be used once. If you did not start a RetailPOS signup, you can ignore this email.',
                ],
            );

            $session->update([
                'verification_delivery_id' => $delivery->id,
                'verification_delivery_status' => $delivery->status,
            ]);
            $this->audit->record('saas.public_signup.otp_delivery_queued', $session, 'Public signup OTP delivery recorded.', [
                'company_id' => $company->id,
                'delivery_id' => $delivery->id,
                'status' => $delivery->status,
            ]);

            return $delivery;
        } catch (Throwable) {
            $session->update([
                'verification_delivery_id' => null,
                'verification_delivery_status' => 'failed',
            ]);
            $this->audit->record('saas.public_signup.otp_delivery_failed', $session, 'Public signup OTP delivery could not be queued.', ['company_id' => $company->id]);
            Log::warning('Public signup OTP delivery could not be queued.', ['signup_session_id' => $session->id]);

            return null;
        }
    }

    public function status(SaasPublicSignupSession $session): ?string
    {
        if ($session->verification_method !== 'email') {
            return null;
        }

        $delivery = $session->verificationDelivery;

        return $delivery?->status ?? $session->verification_delivery_status;
    }

    public function canSendQueued(NotificationDelivery $delivery): bool
    {
        if ($delivery->template_key !== self::TEMPLATE_KEY) {
            return true;
        }

        if ($delivery->related_type !== (new SaasPublicSignupSession)->getMorphClass()) {
            return false;
        }

        $session = SaasPublicSignupSession::query()->find($delivery->related_id);

        return $session
            && $session->verification_delivery_id === $delivery->id
            && ! $session->verified_at
            && ! $session->provisioned_at
            && $session->verification_expires_at?->isFuture()
            && $session->expires_at?->isFuture()
            && filled($session->verification_code_hash);
    }

    private function deliveryCompany(): ?Company
    {
        $configuredId = config('saas.public_signup.email_delivery_company_id');
        if (is_numeric($configuredId)) {
            return Company::query()->whereKey((int) $configuredId)->where('is_active', true)->first();
        }

        return Company::query()
            ->where('is_active', true)
            ->whereHas('users', fn ($query) => $query->where('is_platform_admin', true)->where('is_active', true))
            ->orderBy('id')
            ->first();
    }
}
