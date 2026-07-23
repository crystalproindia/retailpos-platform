<?php

namespace App\Services\Saas;

use App\Data\SaasBilling\PaymentVerification;
use App\Jobs\Saas\ProcessSaasBillingWebhook;
use App\Models\SaasBillingCheckoutSession;
use App\Models\SaasBillingWebhookEvent;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class SaasBillingWebhookService
{
    public function __construct(private readonly SaasPaymentGatewayManager $gateways, private readonly SaasBillingCheckoutService $checkout) {}

    public function receive(string $provider, string $rawPayload, string $signature): SaasBillingWebhookEvent
    {
        $connection = $this->gateways->active();
        if (! $connection || $provider !== 'razorpay') throw new SaasPaymentGatewayException('Billing webhook configuration is unavailable.');
        $gateway = $this->gateways->gateway($connection);
        if (! $gateway->verifyWebhookSignature($rawPayload, $signature)) throw new SaasPaymentGatewayException('Billing webhook signature verification failed.');
        $event = $gateway->normalizeWebhookEvent($rawPayload);
        $session = SaasBillingCheckoutSession::query()->where('provider', $provider)->where('provider_order_id', $event->orderId)->first();

        try {
            $record = SaasBillingWebhookEvent::query()->firstOrCreate(
                ['provider' => $provider, 'provider_event_id' => $event->eventId],
                [
                    'integration_connection_id' => $connection->id, 'company_id' => $session?->company_id, 'event_type' => $event->type,
                    'status' => 'verified', 'signature' => $signature, 'payload_hash' => hash('sha256', $rawPayload), 'raw_payload' => $rawPayload,
                    'normalized_payload' => ['payment_id' => $event->paymentId, 'order_id' => $event->orderId, 'refund_id' => $event->refundId, 'status' => $event->status, 'amount' => $event->amount, 'currency' => $event->currency],
                    'received_at' => now(), 'verified_at' => now(),
                ],
            );
        } catch (QueryException) {
            $record = SaasBillingWebhookEvent::query()->where('provider', $provider)->where('provider_event_id', $event->eventId)->firstOrFail();
        }
        if ($record->wasRecentlyCreated) ProcessSaasBillingWebhook::dispatch($record->id);

        return $record;
    }

    public function process(SaasBillingWebhookEvent $event): void
    {
        DB::transaction(function () use ($event): void {
            $event = SaasBillingWebhookEvent::query()->lockForUpdate()->findOrFail($event->id);
            if ($event->status === 'processed') return;
            if ($event->status !== 'verified') throw new SaasPaymentGatewayException('Only verified billing webhooks may be processed.');
            $payload = $event->normalized_payload ?? [];
            $session = SaasBillingCheckoutSession::query()->with(['invoice', 'integration'])->where('provider', $event->provider)->where('provider_order_id', $payload['order_id'] ?? null)->lockForUpdate()->first();
            if (! $session) {
                $event->update(['status' => 'ignored', 'processed_at' => now()]);
                return;
            }
            if (in_array($event->event_type, ['payment.captured', 'order.paid'], true)) {
                $this->checkout->applyVerification($session, null, new PaymentVerification(
                    (string) $payload['payment_id'], $payload['order_id'] ?? null, 'captured', (string) $payload['amount'], (string) $payload['currency'], metadata: ['webhook_event_id' => $event->provider_event_id],
                ));
            } elseif ($event->event_type === 'payment.failed') {
                $session->update(['status' => 'failed', 'provider_payment_id' => $payload['payment_id'] ?? null, 'failed_at' => now()]);
            }
            $event->update(['status' => 'processed', 'processed_at' => now(), 'attempt_count' => $event->attempt_count + 1]);
        });
    }

    public function fail(SaasBillingWebhookEvent $event): void
    {
        $event->update(['status' => 'failed', 'attempt_count' => $event->attempt_count + 1, 'safe_failure_reason' => 'Verified billing webhook processing could not complete.', 'failed_at' => now()]);
    }
}
