<?php

namespace Tests\Feature;

use Tests\TestCase;

class InvoiceDeliveryTrackingTest extends TestCase
{
    public function test_provider_delivery_tracking_is_disabled_until_explicitly_configured(): void
    {
        config(['email-delivery.webhook.enabled' => false]);

        $this->postJson('/api/email-delivery/generic/webhook', [])->assertNotFound();
    }
}
