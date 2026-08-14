<?php

namespace App\Services\Crm;

use App\Models\Company;
use App\Models\Compliance\GstSetting;
use Illuminate\Validation\ValidationException;

class DocumentTaxModeService
{
    public const GST = 'gst';
    public const NO_GST = 'no_gst';

    public function normalize(Company $company, ?string $mode): string
    {
        $mode = $mode ?: self::GST;
        if (! in_array($mode, [self::GST, self::NO_GST], true)) {
            throw ValidationException::withMessages(['tax_mode' => 'Choose GST or No-GST.']);
        }
        if ($mode === self::NO_GST && ! $this->allowsNoGst($company)) {
            throw ValidationException::withMessages(['tax_mode' => 'No-GST documents are only available when this company is configured as unregistered or exempt in GST Settings.']);
        }

        return $mode;
    }

    public function allowsNoGst(Company $company): bool
    {
        return in_array(
            GstSetting::query()->where('company_id', $company->id)->value('registration_type'),
            ['unregistered', 'exempt'],
            true,
        );
    }
}
