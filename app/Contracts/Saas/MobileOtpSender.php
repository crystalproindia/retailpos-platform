<?php

namespace App\Contracts\Saas;

interface MobileOtpSender
{
    public function isConfigured(): bool;

    public function send(string $mobile, string $code): void;
}
