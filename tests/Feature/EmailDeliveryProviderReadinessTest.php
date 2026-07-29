<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\User;
use App\Services\Notifications\EmailDeliveryProviderRegistry;
use App\Services\Notifications\EmailDeliveryWebhookDiagnostics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailDeliveryProviderReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_generic_smtp_configuration_does_not_claim_a_production_provider_adapter(): void
    {
        config(['email-delivery.provider' => null, 'email-delivery.webhook.enabled' => false]);

        $configuration = app(EmailDeliveryProviderRegistry::class)->configuration();

        $this->assertSame('No provider selected', $configuration['label']);
        $this->assertFalse($configuration['production_adapter']);
        $this->assertFalse($configuration['webhook_enabled']);
    }

    public function test_unknown_provider_is_not_accepted_by_the_generic_webhook_boundary(): void
    {
        config(['email-delivery.webhook.enabled' => true, 'email-delivery.webhook.secret' => 'readiness-secret', 'email-delivery.provider' => 'postmark']);

        $this->postJson('/api/email-delivery/postmark/webhook', [])->assertNotFound();
    }

    public function test_signature_failures_are_counted_without_recording_payload_or_secrets(): void
    {
        config(['email-delivery.webhook.enabled' => true, 'email-delivery.webhook.secret' => 'readiness-secret']);
        $this->postJson('/api/email-delivery/generic/webhook', [])->assertUnauthorized();

        $diagnostics = app(EmailDeliveryWebhookDiagnostics::class)->snapshot();

        $this->assertSame(1, $diagnostics['signature_failures']);
        $this->assertNotNull($diagnostics['last_rejected_at']);
        $this->assertArrayNotHasKey('secret', $diagnostics);
        $this->assertArrayNotHasKey('payload', $diagnostics);
    }

    public function test_administrator_sees_provider_readiness_without_secrets(): void
    {
        $administrator = $this->user();

        $this->actingAs($administrator)->get('/settings/integrations/email')
            ->assertOk()
            ->assertSee('Provider event readiness')
            ->assertSee('No provider selected')
            ->assertDontSee('readiness-secret');
    }

    private function user(): User
    {
        $company = Company::factory()->create();

        return User::factory()->for($company)->create(['role' => UserRole::Administrator]);
    }
}
