<?php

namespace App\Console\Commands\RetailPos;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class RotateAdministratorPasswordCommand extends Command
{
    protected $signature = 'retailpos:admin-password {--email= : Existing Administrator email address}';

    protected $description = 'Safely rotate the password for an existing RetailPOS Administrator.';

    public function handle(): int
    {
        $email = trim((string) ($this->option('email') ?: $this->ask('Administrator email')));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Enter a valid administrator email address.');

            return self::FAILURE;
        }

        $administrator = User::query()
            ->where('email', $email)
            ->where('role', UserRole::Administrator->value)
            ->first();

        if (! $administrator) {
            $this->error('No administrator found for that email.');

            return self::FAILURE;
        }

        $password = (string) $this->secret('New password (minimum 12 characters)');
        $confirmation = (string) $this->secret('Confirm new password');

        if (mb_strlen($password) < 12) {
            $this->error('The password must contain at least 12 characters.');

            return self::FAILURE;
        }

        if (! hash_equals($password, $confirmation)) {
            $this->error('The password confirmation does not match.');

            return self::FAILURE;
        }

        $administrator->forceFill([
            'password' => Hash::make($password),
        ])->save();

        $this->info('Administrator password updated successfully.');

        return self::SUCCESS;
    }
}
