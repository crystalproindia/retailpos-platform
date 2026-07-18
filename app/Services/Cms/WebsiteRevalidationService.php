<?php

namespace App\Services\Cms;

use App\Models\Cms\CmsRevalidationLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebsiteRevalidationService
{
    public function __construct(private readonly PublicCmsService $publicCms) {}

    public function trigger(string $path): bool
    {
        $url = config('services.retailpos.website_revalidate_url');
        $token = config('services.retailpos.website_revalidate_token');

        if (blank($url) || blank($token)) {
            return false;
        }

        try {
            $response = Http::asJson()->timeout(5)->withToken($token)->post($url, ['path' => $this->path($path)]);
            Log::info('Website CMS revalidation attempted.', ['path' => $path, 'successful' => $response->successful(), 'status' => $response->status()]);

            return $response->successful();
        } catch (\Throwable $exception) {
            Log::warning('Website CMS revalidation failed.', ['path' => $path, 'exception' => $exception->getMessage()]);

            return false;
        }
    }

    /** @param array{type?: string, slug?: string} $context */
    public function revalidate(int $companyId, string $path, array $context = []): CmsRevalidationLog
    {
        $url = config('services.retailpos.website_revalidate_url');
        $token = config('services.retailpos.website_revalidate_token');
        $path = $this->path($path);
        $this->publicCms->forgetWebsiteData($companyId, $context['type'] ?? 'content', $context['slug'] ?? null);

        if (blank($url) || blank($token)) {
            return $this->record($companyId, $path, $context, CmsRevalidationLog::STATUS_SKIPPED_NOT_CONFIGURED, null, 'Website revalidation is not configured.');
        }

        try {
            $response = Http::asJson()->timeout(5)->withToken($token)->post($url, [
                'path' => $path,
                'type' => $context['type'] ?? 'content',
                'slug' => $context['slug'] ?? null,
            ]);
            Log::info('Website CMS revalidation attempted.', ['path' => $path, 'successful' => $response->successful(), 'status' => $response->status()]);

            return $this->record(
                $companyId,
                $path,
                $context,
                $response->successful() ? CmsRevalidationLog::STATUS_SUCCESS : CmsRevalidationLog::STATUS_FAILED,
                $response->status(),
                $response->successful() ? 'Website cache refreshed.' : 'Website refresh endpoint returned an unsuccessful response.',
            );
        } catch (\Throwable $exception) {
            Log::warning('Website CMS revalidation failed.', ['path' => $path, 'exception' => $exception->getMessage()]);

            return $this->record($companyId, $path, $context, CmsRevalidationLog::STATUS_FAILED, null, 'Website refresh request could not be completed.');
        }
    }

    /** @param array{type?: string, slug?: string} $context */
    private function record(int $companyId, string $path, array $context, string $status, ?int $responseCode, string $message): CmsRevalidationLog
    {
        return CmsRevalidationLog::create([
            'company_id' => $companyId,
            'content_type' => $context['type'] ?? 'content',
            'slug' => $context['slug'] ?? null,
            'path' => $path,
            'status' => $status,
            'response_code' => $responseCode,
            'message' => $message,
        ]);
    }

    private function path(string $path): string
    {
        return '/'.ltrim($path, '/');
    }
}
