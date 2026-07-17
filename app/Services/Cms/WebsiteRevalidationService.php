<?php

namespace App\Services\Cms;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebsiteRevalidationService
{
    public function trigger(string $path): bool
    {
        $url = config('services.retailpos.website_revalidate_url');
        $token = config('services.retailpos.website_revalidate_token');

        if (blank($url) || blank($token)) {
            return false;
        }

        try {
            $response = Http::asJson()->timeout(5)->withToken($token)->post($url, ['path' => '/'.ltrim($path, '/')]);
            Log::info('Website CMS revalidation attempted.', ['path' => $path, 'successful' => $response->successful(), 'status' => $response->status()]);

            return $response->successful();
        } catch (\Throwable $exception) {
            Log::warning('Website CMS revalidation failed.', ['path' => $path, 'exception' => $exception->getMessage()]);

            return false;
        }
    }
}
