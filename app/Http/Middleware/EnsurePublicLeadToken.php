<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePublicLeadToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('services.retailpos.public_lead_token');
        $provided = (string) $request->header('X-RetailPOS-Lead-Token');

        if ($expected === '' || $provided === '' || ! hash_equals($expected, $provided)) {
            return response()->json(['message' => 'Unauthenticated.'], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
