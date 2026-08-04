<?php

namespace App\Services\Branding;

use App\Models\Company;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class CompanyBrandingService
{
    private const DISK = 'local';

    /** @var array<string, string> */
    private const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @return array{company_logo: ?string, invoice_logo: ?string, active_logo: ?string, source: ?string}
     */
    public function forCompany(Company $company): array
    {
        $invoiceLogo = $this->dataUri($company->invoice_logo_path);
        $companyLogo = $this->dataUri($company->company_logo_path);

        return [
            'company_logo' => $companyLogo,
            'invoice_logo' => $invoiceLogo,
            'active_logo' => $invoiceLogo ?: $companyLogo,
            'source' => $invoiceLogo ? 'invoice_override' : ($companyLogo ? 'company' : null),
        ];
    }

    /**
     * @return array{data_uri: ?string, source: ?string}
     */
    public function forInvoice(Company $company): array
    {
        $branding = $this->forCompany($company);

        return ['data_uri' => $branding['active_logo'], 'source' => $branding['source']];
    }

    public function replace(Company $company, User $user, UploadedFile $file, string $kind): Company
    {
        $field = $this->fieldFor($kind);
        $extension = self::EXTENSIONS[$file->getMimeType() ?: ''] ?? null;

        if ($extension === null) {
            throw new RuntimeException('The uploaded branding file is not a supported image.');
        }

        $path = sprintf('companies/%d/branding/%s.%s', $company->id, Str::uuid(), $extension);
        $stored = Storage::disk(self::DISK)->put($path, $file->getContent());

        if (! $stored) {
            throw new RuntimeException('The branding file could not be stored.');
        }

        $previousPath = $company->{$field};

        try {
            DB::transaction(function () use ($company, $field, $path, $user, $kind, $previousPath): void {
                $company->forceFill([$field => $path])->save();
                $this->audit->record(
                    $previousPath ? "company.branding.{$kind}.replaced" : "company.branding.{$kind}.uploaded",
                    $company,
                    $previousPath ? 'Company branding logo replaced.' : 'Company branding logo uploaded.',
                    [
                        'company_id' => $company->id,
                        'user_id' => $user->id,
                        'logo_kind' => $kind,
                        'had_previous_logo' => (bool) $previousPath,
                    ],
                );
            });
        } catch (\Throwable $exception) {
            Storage::disk(self::DISK)->delete($path);

            throw $exception;
        }

        if ($previousPath && $previousPath !== $path) {
            Storage::disk(self::DISK)->delete($previousPath);
        }

        return $company->refresh();
    }

    public function remove(Company $company, User $user, string $kind): Company
    {
        $field = $this->fieldFor($kind);
        $previousPath = $company->{$field};

        if (! $previousPath) {
            return $company;
        }

        DB::transaction(function () use ($company, $field, $user, $kind): void {
            $company->forceFill([$field => null])->save();
            $this->audit->record(
                "company.branding.{$kind}.removed",
                $company,
                'Company branding logo removed.',
                ['company_id' => $company->id, 'user_id' => $user->id, 'logo_kind' => $kind],
            );
        });

        Storage::disk(self::DISK)->delete($previousPath);

        return $company->refresh();
    }

    private function fieldFor(string $kind): string
    {
        return match ($kind) {
            'company' => 'company_logo_path',
            'invoice' => 'invoice_logo_path',
            default => throw new RuntimeException('Unknown company branding logo type.'),
        };
    }

    private function dataUri(?string $path): ?string
    {
        if (! $path || ! Storage::disk(self::DISK)->exists($path)) {
            return null;
        }

        $mime = Storage::disk(self::DISK)->mimeType($path);
        if (! isset(self::EXTENSIONS[$mime ?: ''])) {
            return null;
        }

        $contents = Storage::disk(self::DISK)->get($path);

        return $contents === null ? null : 'data:'.$mime.';base64,'.base64_encode($contents);
    }
}
