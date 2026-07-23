<?php

namespace App\Services\Saas;

use App\Contracts\SaasBilling\PaymentGateway;
use App\Data\SaasBilling\CheckoutSession;
use App\Data\SaasBilling\GatewayCustomerReference;
use App\Data\SaasBilling\PaymentIntent;
use App\Data\SaasBilling\PaymentVerification;
use App\Data\SaasBilling\RefundRequest;
use App\Data\SaasBilling\WebhookEvent;
use App\Models\IntegrationConnection;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class RazorpayPaymentGateway implements PaymentGateway
{
    public function __construct(private readonly IntegrationConnection $connection) {}

    public function provider(): string { return 'razorpay'; }

    public function createCheckout(CheckoutSession $session): PaymentIntent
    {
        $response = $this->client()->post('orders', [
            'amount' => $this->paise($session->amount),
            'currency' => $session->currency,
            'receipt' => $session->receipt,
            'notes' => ['checkout_session_id' => (string) $session->id, 'invoice_id' => (string) $session->invoiceId, 'subscription_id' => (string) $session->subscriptionId],
        ]);
        $this->ensureSuccess($response->json(), $response->successful());

        return new PaymentIntent((string) $response->json('id'), (string) $response->json('status', 'created'), ['key_id' => $this->keyId()]);
    }

    public function verifyPayment(string $paymentId, string $orderId, string $signature): PaymentVerification
    {
        $expected = hash_hmac('sha256', $orderId.'|'.$paymentId, $this->keySecret());
        if (! hash_equals($expected, $signature)) {
            throw new SaasPaymentGatewayException('The payment signature could not be verified.');
        }

        return $this->fetchPayment($paymentId);
    }

    public function fetchPayment(string $paymentId): PaymentVerification
    {
        $response = $this->client()->get('payments/'.rawurlencode($paymentId));
        $this->ensureSuccess($response->json(), $response->successful());
        $data = $response->json();

        return new PaymentVerification(
            paymentId: (string) ($data['id'] ?? $paymentId), orderId: $data['order_id'] ?? null,
            status: (string) ($data['status'] ?? 'unknown'), amount: $this->decimal((int) ($data['amount'] ?? 0)),
            currency: (string) ($data['currency'] ?? 'INR'), method: $data['method'] ?? null,
            failureCode: $data['error_code'] ?? null, failureMessage: $data['error_description'] ?? null,
            metadata: ['provider_status' => $data['status'] ?? null],
        );
    }

    public function refundPayment(RefundRequest $request): array
    {
        $response = $this->client()->post('payments/'.rawurlencode($request->paymentId).'/refund', ['amount' => $this->paise($request->amount), 'notes' => $request->metadata]);
        $this->ensureSuccess($response->json(), $response->successful());

        return ['provider_refund_id' => (string) $response->json('id'), 'status' => (string) $response->json('status', 'processed')];
    }

    public function verifyWebhookSignature(string $rawPayload, string $signature): bool
    {
        return filled($signature) && hash_equals(hash_hmac('sha256', $rawPayload, $this->webhookSecret()), $signature);
    }

    public function normalizeWebhookEvent(string $rawPayload): WebhookEvent
    {
        $data = json_decode($rawPayload, true, 512, JSON_THROW_ON_ERROR);
        $payment = $data['payload']['payment']['entity'] ?? [];
        $refund = $data['payload']['refund']['entity'] ?? [];

        return new WebhookEvent(
            eventId: (string) ($data['event_id'] ?? $data['id'] ?? hash('sha256', $rawPayload)), type: (string) ($data['event'] ?? 'unknown'),
            paymentId: $payment['id'] ?? ($refund['payment_id'] ?? null), orderId: $payment['order_id'] ?? null,
            refundId: $refund['id'] ?? null, status: $payment['status'] ?? ($refund['status'] ?? null),
            amount: isset($payment['amount']) ? $this->decimal((int) $payment['amount']) : (isset($refund['amount']) ? $this->decimal((int) $refund['amount']) : null),
            currency: $payment['currency'] ?? ($refund['currency'] ?? null), payload: $data,
        );
    }

    public function customerReference(?string $email, ?string $name): GatewayCustomerReference
    {
        return new GatewayCustomerReference(null, array_filter(['email' => $email, 'name' => $name]));
    }

    public function keyId(): string { return (string) (($this->connection->settings ?? [])['key_id'] ?? ''); }
    private function keySecret(): string { return (string) $this->connection->access_token; }
    private function webhookSecret(): string { return (string) $this->connection->refresh_token; }
    private function client(): PendingRequest { return Http::baseUrl(rtrim((string) config('services.razorpay.base_url', 'https://api.razorpay.com/v1'), '/'))->acceptJson()->asJson()->withBasicAuth($this->keyId(), $this->keySecret())->timeout(15); }
    private function paise(string $amount): int { return (int) round(((float) $amount) * 100); }
    private function decimal(int $minor): string { return number_format($minor / 100, 2, '.', ''); }
    /** @param array<string,mixed>|null $payload */
    private function ensureSuccess(?array $payload, bool $successful): void { if (! $successful) throw new SaasPaymentGatewayException((string) ($payload['error']['description'] ?? 'The payment gateway request could not be completed.')); }
}
