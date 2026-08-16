<?php

namespace App\Services\Crm;

use App\Models\Crm\CrmInvoice;
use App\Models\Crm\CrmProformaInvoice;
use App\Models\Crm\CrmQuotation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class InvoiceWatermarkService
{
    private const DISK = 'local';

    /** @var array<string, string> */
    private const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function store(UploadedFile $file, int $companyId): string
    {
        $mime = $file->getMimeType() ?: '';
        $extension = self::EXTENSIONS[$mime] ?? null;

        if (! $file->isValid() || $extension === null || $file->getSize() > 2 * 1024 * 1024 || ! @getimagesize($file->getRealPath())) {
            throw new RuntimeException('The watermark must be a valid PNG, JPG, or WEBP image up to 2 MB.');
        }

        $path = sprintf('companies/%d/invoice-watermarks/%s.%s', $companyId, Str::uuid(), $extension);

        if (! Storage::disk(self::DISK)->put($path, $file->getContent())) {
            throw new RuntimeException('The watermark image could not be stored.');
        }

        return $path;
    }

    public function dataUri(?string $path): ?string
    {
        if (! $path || ! Storage::disk(self::DISK)->exists($path)) {
            return null;
        }

        $mime = Storage::disk(self::DISK)->mimeType($path) ?: '';
        if (! isset(self::EXTENSIONS[$mime])) {
            return null;
        }

        return 'data:'.$mime.';base64,'.base64_encode(Storage::disk(self::DISK)->get($path));
    }

    public function delete(string $path): void
    {
        Storage::disk(self::DISK)->delete($path);
    }

    public function deleteIfUnreferenced(?string $path): void
    {
        if (! $path || CrmInvoice::query()->where('watermark_path_snapshot', $path)->exists()
            || CrmQuotation::query()->where('watermark_path_snapshot', $path)->exists()
            || CrmProformaInvoice::query()->where('watermark_path_snapshot', $path)->exists()) {
            return;
        }

        $this->delete($path);
    }
}
