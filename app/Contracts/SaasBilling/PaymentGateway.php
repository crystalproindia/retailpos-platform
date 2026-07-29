<?php

namespace App\Contracts\SaasBilling;

use App\Data\SaasBilling\CheckoutSession;
use App\Data\SaasBilling\GatewayCustomerReference;
use App\Data\SaasBilling\PaymentIntent;
use App\Data\SaasBilling\PaymentVerification;
use App\Data\SaasBilling\RefundRequest;
use App\Data\SaasBilling\WebhookEvent;

interface PaymentGateway
{
    public function provider(): string;
    public function createCheckout(CheckoutSession $session): PaymentIntent;
    public function verifyPayment(string $paymentId, string $orderId, string $signature): PaymentVerification;
    public function fetchPayment(string $paymentId): PaymentVerification;
    public function refundPayment(RefundRequest $request): array;
    public function verifyWebhookSignature(string $rawPayload, string $signature): bool;
    public function normalizeWebhookEvent(string $rawPayload): WebhookEvent;
    public function customerReference(?string $email, ?string $name): GatewayCustomerReference;
}
