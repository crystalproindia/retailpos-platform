<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Notifications\EmailDeliveryProviderEventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EmailDeliveryWebhookController extends Controller
{
    public function __invoke(Request $request, EmailDeliveryProviderEventService $events, string $provider): JsonResponse
    {
        abort_unless(config('email-delivery.webhook.enabled', false), 404);

        $signature = (string) $request->header('X-RetailPOS-Email-Signature');
        $secret = (string) config('email-delivery.webhook.secret');
        $expected = $secret ? 'sha256='.hash_hmac('sha256', $request->getContent(), $secret) : '';
        abort_unless($secret && $signature && hash_equals($expected, $signature), 401);

        try {
            $processed = $events->process($provider, $request->validate([
                'company_id' => ['required', 'integer'],
                'event_id' => ['required', 'string', 'max:191'],
                'event_type' => ['required', 'string', 'max:80'],
                'provider_message_id' => ['required', 'string', 'max:191'],
                'timestamp' => ['required', 'date'],
            ]));
        } catch (ValidationException) {
            return response()->json(['message' => 'Delivery event rejected.'], 422);
        }

        return response()->json(['accepted' => true, 'duplicate' => ! $processed]);
    }
}
