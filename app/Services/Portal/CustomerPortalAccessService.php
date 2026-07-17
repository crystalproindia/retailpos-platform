<?php

namespace App\Services\Portal;

use App\Models\Crm\CrmCustomer;
use App\Models\Crm\CrmCustomerPortalToken;
use App\Models\Crm\CrmCustomerPortalUser;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CustomerPortalAccessService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /** @param array{name: string, email: string, phone?: string|null} $data */
    public function invite(User $actor, CrmCustomer $customer, array $data): array
    {
        return DB::transaction(function () use ($actor, $customer, $data): array {
            $portalUser = CrmCustomerPortalUser::query()->updateOrCreate(
                ['customer_id' => $customer->id, 'email' => Str::lower($data['email'])],
                ['name' => $data['name'], 'phone' => $data['phone'] ?? null, 'status' => 'invited', 'created_by' => $actor->id],
            );

            $token = $this->issueToken($portalUser, 'invitation');
            $this->auditLogger->record('crm.customer.portal_invited', $customer, 'Customer portal access invited', ['portal_user_id' => $portalUser->id]);

            return ['portalUser' => $portalUser, 'url' => route('portal.access', ['token' => $token])];
        });
    }

    public function issueLoginLink(CrmCustomerPortalUser $portalUser): string
    {
        return route('portal.access', ['token' => $this->issueToken($portalUser, 'login')]);
    }

    public function consume(string $rawToken): ?CrmCustomerPortalUser
    {
        $token = CrmCustomerPortalToken::query()
            ->with('portalUser.customer')
            ->where('token_hash', hash('sha256', $rawToken))
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();

        if (! $token || $token->portalUser->status === 'suspended') {
            return null;
        }

        return DB::transaction(function () use ($token): CrmCustomerPortalUser {
            $token->update(['used_at' => now()]);
            $token->portalUser->update(['status' => 'active', 'last_login_at' => now()]);
            $this->auditLogger->record('crm.customer.portal_accessed', $token->portalUser->customer, 'Customer portal access activated', ['portal_user_id' => $token->portalUser->id]);

            return $token->portalUser->refresh()->load('customer');
        });
    }

    public function setStatus(CrmCustomerPortalUser $portalUser, string $status): void
    {
        $portalUser->update(['status' => $status]);
        $this->auditLogger->record('crm.customer.portal_status_updated', $portalUser->customer, 'Customer portal access status updated', ['portal_user_id' => $portalUser->id, 'status' => $status]);
    }

    private function issueToken(CrmCustomerPortalUser $portalUser, string $purpose): string
    {
        $rawToken = Str::random(64);
        CrmCustomerPortalToken::query()->where('customer_portal_user_id', $portalUser->id)->whereNull('used_at')->update(['used_at' => now()]);
        $portalUser->tokens()->create(['token_hash' => hash('sha256', $rawToken), 'purpose' => $purpose, 'expires_at' => now()->addDays(7)]);

        return $rawToken;
    }
}
