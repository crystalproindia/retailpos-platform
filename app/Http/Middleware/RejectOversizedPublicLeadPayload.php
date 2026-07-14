<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RejectOversizedPublicLeadPayload
{
    public function handle(Request $request, Closure $next): Response
    {
        $maxBytes = max(1, (int) config('services.retailpos.public_lead_max_payload_kb', 64)) * 1024;
        $contentLength = (int) $request->header('Content-Length', 0);

        if ($contentLength > $maxBytes || strlen($request->getContent()) > $maxBytes) {
            return response()->json(['message' => 'Payload too large.'], Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        return $next($request);
    }
}
