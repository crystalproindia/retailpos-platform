<?php

namespace App\Services\Saas;

use App\Models\AccountVerification;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AccountVerificationService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function issue(User $user, string $channel): string
    {
        $destination = $channel === 'email' ? $user->email : $user->mobile;
        if (! $destination) throw ValidationException::withMessages([$channel => 'No '.$channel.' destination is available for verification.']);

        $latest = AccountVerification::query()->where('user_id', $user->id)->where('channel', $channel)->whereNull('consumed_at')->latest('id')->first();
        if ($latest?->resend_available_at?->isFuture()) {
            throw ValidationException::withMessages([$channel => 'Please wait before requesting another code.']);
        }

        $code = (string) random_int(100000, 999999);
        AccountVerification::query()->where('user_id', $user->id)->where('channel', $channel)->whereNull('consumed_at')->update(['consumed_at' => now()]);
        AccountVerification::create([
            'user_id' => $user->id,
            'channel' => $channel,
            'destination' => $destination,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes((int) config('saas.verification.code_ttl_minutes', 10)),
            'resend_available_at' => now()->addSeconds((int) config('saas.verification.resend_cooldown_seconds', 60)),
            'max_attempts' => (int) config('saas.verification.max_attempts', 5),
        ]);
        $this->audit->record('saas.account_verification.issued', $user, 'Account verification code issued.', ['company_id' => $user->company_id, 'channel' => $channel]);

        return $code;
    }

    public function verify(User $user, string $channel, string $code): void
    {
        $record = AccountVerification::query()->where('user_id', $user->id)->where('channel', $channel)->whereNull('consumed_at')->latest('id')->first();
        if (! $record || $record->expires_at->isPast() || $record->attempt_count >= $record->max_attempts) {
            throw ValidationException::withMessages(['code' => 'This verification code is no longer valid.']);
        }
        $record->increment('attempt_count');
        if (! Hash::check($code, $record->code_hash)) {
            throw ValidationException::withMessages(['code' => 'The verification code is incorrect.']);
        }

        $record->update(['consumed_at' => now()]);
        $user->update(['verification_status' => 'verified', 'verification_completed_at' => now()]);
        $this->audit->record('saas.account_verification.completed', $user, 'Account verification completed.', ['company_id' => $user->company_id, 'channel' => $channel]);
    }
}
