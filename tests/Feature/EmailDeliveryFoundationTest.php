<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\CompanyEmailSetting;
use App\Models\NotificationDelivery;
use App\Models\User;
use App\Services\Notifications\EmailDeliveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EmailDeliveryFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_save_encrypted_company_smtp_settings_without_exposing_the_password(): void
    {
        $administrator = $this->user(UserRole::Administrator);

        $this->actingAs($administrator)->put('/settings/integrations/email', $this->smtpPayload(['password' => 'smtp-secret']))
            ->assertRedirect();

        $setting = CompanyEmailSetting::query()->firstOrFail();
        $this->assertSame('smtp-secret', $setting->password);
        $this->assertNotSame('smtp-secret', $setting->getRawOriginal('password'));

        $this->actingAs($administrator)->put('/settings/integrations/email', $this->smtpPayload(['password' => '']))->assertRedirect();
        $this->assertSame('smtp-secret', $setting->fresh()->password);
        $this->actingAs($administrator)->delete('/settings/integrations/email/password')->assertRedirect();
        $this->assertNull($setting->fresh()->password);
    }

    public function test_sales_cannot_view_or_manage_smtp_settings(): void
    {
        $sales = $this->user(UserRole::Sales);
        $this->actingAs($sales)->get('/settings/integrations/email')->assertForbidden();
        $this->actingAs($sales)->put('/settings/integrations/email', $this->smtpPayload())->assertForbidden();
    }

    public function test_test_email_is_queued_and_missing_smtp_is_logged_as_skipped(): void
    {
        Queue::fake();
        $administrator = $this->user(UserRole::Administrator);

        $this->actingAs($administrator)->post('/settings/integrations/email/test', ['recipient' => 'test@example.test'])
            ->assertRedirect()->assertSessionHas('error');
        $this->assertDatabaseHas('notification_deliveries', ['recipient' => 'test@example.test', 'template_key' => 'test_email', 'status' => 'skipped_not_configured']);

        $this->actingAs($administrator)->post('/settings/integrations/email/test', ['recipient' => 'not-an-email'])
            ->assertSessionHasErrors('recipient');
    }

    public function test_delivery_records_are_tenant_scoped_and_idempotent(): void
    {
        $administrator = $this->user(UserRole::Administrator);
        $other = $this->user(UserRole::Administrator);
        $service = app(EmailDeliveryService::class);
        $first = $service->queue($administrator->company_id, 'sales@example.test', 'Lead received', 'lead_received_internal', ['message' => 'A lead needs review.'], idempotencyKey: 'same-event');
        $second = $service->queue($administrator->company_id, 'sales@example.test', 'Lead received', 'lead_received_internal', ['message' => 'A lead needs review.'], idempotencyKey: 'same-event');

        $this->assertSame($first->id, $second->id);
        NotificationDelivery::query()->whereKey($first)->update(['status' => 'failed']);
        $this->actingAs($other)->get('/settings/email-deliveries')->assertOk()->assertDontSee('sales@example.test');
    }

    /** @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function smtpPayload(array $overrides = []): array
    {
        return $overrides + ['is_enabled' => '1', 'host' => 'smtp.example.test', 'port' => 587, 'encryption' => 'tls', 'username' => 'mailer@example.test', 'from_name' => 'RetailPOS', 'from_address' => 'hello@example.test', 'reply_to_address' => 'support@example.test'];
    }

    private function user(UserRole $role): User
    {
        $company = Company::factory()->create();

        return User::factory()->create(['company_id' => $company->id, 'role' => $role]);
    }
}
