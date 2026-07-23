<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Saas\SaasBillingWebhookService;
use App\Services\Saas\SaasPaymentGatewayException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SaasBillingWebhookController extends Controller
{
    public function __invoke(Request $request, SaasBillingWebhookService $webhooks): JsonResponse
    {
        try {
            $event = $webhooks->receive('razorpay', $request->getContent(), (string) $request->header('X-Razorpay-Signature'));

            return response()->json(['received' => true, 'event_id' => $event->id], 202);
        } catch (SaasPaymentGatewayException) {
            return response()->json(['message' => 'Webhook verification failed.'], 400);
        } catch (\JsonException) {
            return response()->json(['message' => 'Webhook payload was invalid.'], 422);
        }
    }
}
