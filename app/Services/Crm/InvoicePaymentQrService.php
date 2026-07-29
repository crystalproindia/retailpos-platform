<?php

namespace App\Services\Crm;

use App\Models\Crm\CrmInvoice;
use App\Models\InvoiceTemplateSetting;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;

class InvoicePaymentQrService
{
    /** @return array{payload:string,data_uri:string}|null */
    public function forInvoice(CrmInvoice $invoice, InvoiceTemplateSetting $setting): ?array
    {
        if ((float) $invoice->balance_due <= 0 || $invoice->status?->isTerminal()) {
            return null;
        }

        $payload = $this->normalize(
            $setting->payment_qr_uri,
            (string) $invoice->balance_due,
            $invoice->currency,
            $invoice->company->legal_name ?: $invoice->company->name,
        );

        if ($payload === null) {
            return null;
        }

        $result = (new PngWriter)->write(new QrCode(
            data: $payload,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 260,
            margin: 12,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
        ));

        return ['payload' => $payload, 'data_uri' => $result->getDataUri()];
    }

    public function isValidSource(?string $source): bool
    {
        return $this->normalize($source, '1.00', 'INR', 'RetailPOS tenant') !== null;
    }

    private function normalize(?string $source, string $amount, string $currency, string $payeeName): ?string
    {
        if (! is_string($source) || ($source = trim($source)) === '' || strlen($source) > 512) {
            return null;
        }

        $trustedAmount = number_format(max(0, (float) $amount), 2, '.', '');
        $trustedCurrency = strtoupper(trim($currency));
        if (! preg_match('/^[A-Z]{3}$/', $trustedCurrency)) {
            return null;
        }

        if ($this->isUpiId($source)) {
            return $this->upiPayload(['pa' => $source, 'pn' => $payeeName], $trustedAmount, $trustedCurrency);
        }

        $parts = parse_url($source);
        if (! is_array($parts) || ! isset($parts['scheme'])) {
            return null;
        }

        if (strtolower($parts['scheme']) === 'upi') {
            if (strtolower($parts['host'] ?? '') !== 'pay' || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) {
                return null;
            }
            parse_str($parts['query'] ?? '', $query);

            return $this->upiPayload($query, $trustedAmount, $trustedCurrency);
        }

        if (strtolower($parts['scheme']) !== 'https') {
            return null;
        }

        return $this->httpsPayload($parts, $trustedAmount, $trustedCurrency);
    }

    /** @param array<string,mixed> $query */
    private function upiPayload(array $query, string $amount, string $currency): ?string
    {
        $payee = is_string($query['pa'] ?? null) ? trim($query['pa']) : '';
        if (! $this->isUpiId($payee)) {
            return null;
        }

        $safe = ['pa' => $payee];
        foreach (['pn' => 120, 'tn' => 160, 'tr' => 80, 'mc' => 12] as $key => $limit) {
            if (is_string($query[$key] ?? null) && ($value = trim($query[$key])) !== '') {
                $safe[$key] = mb_substr(preg_replace('/[[:cntrl:]]/u', '', $value) ?? '', 0, $limit);
            }
        }
        $safe['am'] = $amount;
        $safe['cu'] = $currency;

        return 'upi://pay?'.http_build_query($safe, '', '&', PHP_QUERY_RFC3986);
    }

    /** @param array<string,mixed> $parts */
    private function httpsPayload(array $parts, string $amount, string $currency): ?string
    {
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (
            $host === '' ||
            isset($parts['user']) ||
            isset($parts['pass']) ||
            isset($parts['fragment']) ||
            $host === 'localhost' ||
            str_ends_with($host, '.local') ||
            ! filter_var('https://'.$host, FILTER_VALIDATE_URL) ||
            preg_match('/[[:cntrl:]\\\\]/u', (string) ($parts['path'] ?? ''))
        ) {
            return null;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) && ! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return null;
        }

        parse_str((string) ($parts['query'] ?? ''), $query);
        foreach (array_keys($query) as $key) {
            if (! is_string($key) || preg_match('/(?:auth|credential|key|password|secret|signature|token)/i', $key)) {
                return null;
            }
        }
        foreach ($query as $value) {
            if (! is_scalar($value)) {
                return null;
            }
        }
        $query['amount'] = $amount;
        $query['currency'] = $currency;

        $port = isset($parts['port']) ? ':'.(int) $parts['port'] : '';
        $path = isset($parts['path']) && $parts['path'] !== '' ? $parts['path'] : '/';

        return 'https://'.$host.$port.$path.'?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    private function isUpiId(string $value): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{1,255}@[A-Za-z0-9][A-Za-z0-9.-]{1,63}$/', $value);
    }
}
